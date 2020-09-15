<?php


namespace parsing\platforms\google;

use Exception;
use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\GetterInterface;
use Google_Client;
use parsing\logger\LoggerManager;

/**
 * Class GoogleGetter
 * @package parsing\platforms\google
 * По сути дела все методы класса - это работа с полем $mainData,
 * поэтому многие методы ничего не возвращают,т.к. напрямую работают с этим полем
 * todo:Не очень хорошо давать доступ к БД геттеру,над этим исправлением надо подумать(возможно слой сервисов)
 *
 */
class GoogleGetter  implements GetterInterface
{

    const URL = 'https://mybusiness.googleapis.com/v4/';
    const PAGE_SIZE = '50';
    const CONTINUE = 777;
    const LAST_ITERATION = 'last_iteration';

    /**
     * @var Google_Client
     * Экземпляр класса Библиотеки гугл
     */
    private $client;

    /**
     * @var DatabaseShell
     * Экземпляр класса оболочки над БД
     */
    private $dataBase;

    /**
     * @var string
     * Источник из базы данных в формате "accounts/{accountId}/locations/{locationId}"
     */
    protected $source;

    /**
     * @var string
     * Флаг из БД,говорящий о том,обработанная ссылка или нет
     */
    protected $handled;

    /**
     * @var int|string
     * Статус объекта
     */
    private $status = self::CONTINUE;

    /**
     * @var int
     * Временная метка в полгода назад
     */
    private $halfYearAgo;

    /**
     * @var int
     * Количество выполнений метода GetNextRecords
     */
    private $iteration = 0;

    /**
     * @var array|int
     */
    private $mainData;

    /**
     * @var int
     * Дата последнего отзыва,лежащего в БД.
     */
    private $last_review_db;

    /**
     * GoogleGetter constructor.
     * @param Google_Client $client
     * @param DatabaseShell $dataBase
     * @throws Exception
     * Установка экземпляров класса.
     * Инициализация конфигов гугла.В случае ошибки ловит исключение,записывает в лог и ставит статусу значение
     * о прекращении работы
     */
    public function __construct(Google_Client $client,DatabaseShell $dataBase)
    {
        $this->client = $client;
        $this->dataBase = $dataBase;
        $this->halfYearAgo=time()-self::HALF_YEAR_TIMESTAMP;

        try{
            $this->client->setAuthConfig(__DIR__.'/secret.json');
        }catch (Exception $e){
            LoggerManager::log(LoggerManager::ALERT,
                                    'Problems with secret.json|GoogleGetter',
                                            ['exception_message'=>$e->getMessage()]);
            $this->status = self::END_CODE;
        }
    }

    /**
     *
     * Функция,которая вызывает все остальные методы и возвращает массив данных с GMB_API в необработанном виде.
     * При отсутсвии отзывов или того,что уже совершилась последняя итерация, возвращает метаинформацию.
     * При первичной обработке происходит получение всех отзывов за последние пол-года.
     * При вторичной обработке происходит сверка хэшей конфига и первого отзыва,
     * полученного через GoogleMyBusiness API.
     * Сначала метод выполняет общие действия для обоих видов обработки,затем происходит разделение методов в зависимости
     * от флага $handled.
     *
     */
    public function getNextRecords()
    {
        $this->preparingActions();

        if($this->mayProcessNewOrNonCompleted()){

                $this->formMainData($this->halfYearAgo);

        }elseif($this->mayProcessHandledOrNonUpdated()){

            $lastReviewFromSource = $this->arrayReviewToMd5Hash();
            $lastReviewHash = $this->mainData['config']['last_review_hash'];

            if($lastReviewFromSource === $lastReviewHash){
                $this->mainData['platform_info']['reviews'] = [];
                $this->status = self::END_CODE;
            }else{
                $this->formMainData($this->last_review_db);
            }
        }
        $this->checkMainData();
        return $this->mainData;
    }

    /**
     * @param int $timeToCut
     * Метод устанавливает конфиги и обрезает отзывы до указанной даты в массив mainData
     */
    private function formMainData(int $timeToCut):void
    {
        if($this->iteration === 1){
            $this->setLastReviewConfig();
        }
        $this->cutToTime($timeToCut);
    }

    /**
     * Метод производит действия,независящие от флага handled:
     * Проверяет свойство статус
     * Обновляет токен
     * Подключается к API
     * Проверяет ответ от API
     */
    private function preparingActions():void
    {
        $this->iteration++;

        if ($this->status === self::END_CODE) {
            $this->mainData = self::END_CODE;
        }elseif($this->status === self::LAST_ITERATION){
            $this->mainData['platform_info']['reviews'] = [];
            $this->status = self::END_CODE;
        }else{
            $this->refreshToken();
            $this->connectToAPI();
            $this->checkResponse();
        }
    }
    /**
     * Метод проверяет ответ от API.
     * В случае,если нет отзывов,то ставит статус окончание работы работы
     */
    private function checkResponse():void
    {
        if (empty($this->mainData['platform_info']['reviews'])){
            $this->status = self::END_CODE;
        }
    }

    /**
     * Проверяет данные перед отправкой
     */
    private function checkMainData():void {
        if(empty($this->mainData['platform_info']['reviews'])){
            $this->status = self::END_CODE;
        }
        if(empty($this->mainData['platform_info']['nextPageToken'])){
            $this->status = self::LAST_ITERATION;
        }
    }

    /**
     * @param $config
     * Парсинг коннфигов:выставление $handled,$source и конфигов ссылки.
     * В случае невалидности конфигов записывает это в логгер
     */
    public function setConfig($config)
    {

        if($this->validateConfig($config)){
            $decode_config = json_decode($config['config'],true);
            $this->source = $config['source'];
            $this->handled = $config['handled'];
            $this->mainData['config'] = $decode_config;


            if($this->handled === self::SOURCE_HANDLED){
                $this->last_review_db = $decode_config['last_review_date'];
            }
        }else{
            LoggerManager::log(LoggerManager::ERROR,
                                    'Invalid config values|GoogleGetter',
                                            ['config'=>$config]
                              );
            $this->dataBase->updateSourceReview($config['source_hash'], ['handled'=>self::SOURCE_NON_COMPLETED]);
            $this->status = self::END_CODE;

        }


    }

    /**
     * записывает дату и хэш самого последнего по времени отзыва.
     */
    private function setLastReviewConfig():void
    {
            $lastReview = $this->mainData['platform_info']['reviews'][0];
            $lastReviewDate = empty($lastReview) ?$this->halfYearAgo : strtotime($lastReview['updateTime']);

            $this->mainData['config']['last_review_date'] = $lastReviewDate;
            $this->mainData['config']['last_review_hash'] = $this->arrayReviewToMd5Hash();
    }


    /**
     * Обновляет токен и перезаписвает конфиги,записывая туда обновлённый токен
     */
    private function refreshToken():void
    {

        $this->client->setAccessToken($this->mainData['config']['token_info']);
        if($this->client->isAccessTokenExpired()){
            $refreshToken = $this->client->getRefreshToken();
            $this->mainData['config']['token_info'] = $this->client
                                                      ->fetchAccessTokenWithRefreshToken($refreshToken);
        }
    }

    /**
     * @param array $config
     * @return bool
     * Метод проверяет валидность конфигов
     */
    private function validateConfig(array $config):bool
    {
        $configIsValid = true;
        $handledNotExist = !array_key_exists('handled', $config);
        $sourceConfigNotExist = !array_key_exists('config',$config);
        if($handledNotExist || $sourceConfigNotExist){
            $configIsValid = false;
        }
        return $configIsValid;

    }

    /**
     * @param int $timeBreak-дата,которая  используется при проверке в timestamp.
     * Функция обрезает данные до установленной даты.
     */
    private function cutToTime(int $timeBreak):void
    {
        $data = $this->mainData['platform_info']['reviews'];

        for($i=0;$i<count($data)-1;$i++){

            $timeStamp=strtotime($data[$i]['updateTime']);

            if($timeStamp <= $timeBreak ){
                $data=array_slice($data,0,$i);
                $this->mainData['platform_info']['reviews'] = $data;
                break;
            }
        }
    }

    /**
     *
     * Метод подключается к Google API по данному $source.
     * Если имеется nextPageToken,то он используется,также формируется заголовок запроса с нужным токеном
     * Устанавливает в $mainData ответ запроса
     */
    private function connectToAPI():void
    {
        $request_url = self::URL.$this->source.'/reviews?pageSize='.self::PAGE_SIZE;

        if(!empty($this->mainData['platform_info']['nextPageToken'])){
            $request_url = $request_url .'&pageToken=' . $this->mainData['platform_info']['nextPageToken'];
        }

        $curl = curl_init($request_url);

        $token_type = $this->mainData['config']['token_info']['token_type'];
        $access_token = $this->mainData['config']['token_info']['access_token'];

        $header_str = 'Authorization: '.$token_type.' '.$access_token;

        curl_setopt($curl,CURLOPT_HTTPHEADER,[$header_str]);
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);

        $response = curl_exec($curl);

        $this->mainData['platform_info'] = json_decode($response,true);
    }

    /**
     * @return string
     * Переводит массив самого первого отзыва в хэш строку
     */
    private function arrayReviewToMd5Hash(): string
    {
            $lastUpdateReview = $this->mainData['platform_info']['reviews'][0];
            foreach ($lastUpdateReview as &$v){
                if(is_array($v)){
                    $v = implode($v,'');
                }
            }
            $implode_array = implode($lastUpdateReview,'');
            return md5($implode_array);
    }

    /**
     * @return bool
     * Метод нужен для сокращения кода в методе GetNextRecords
     */
    private function mayProcessHandledOrNonUpdated():bool
    {
        return ($this->handled === self::SOURCE_HANDLED) || ($this->handled === self::SOURCE_NON_UPDATED) && $this->status === self::CONTINUE;
    }

    /**
     * @return bool
     * Метод нужен для сокращения кода в методе GetNextRecords
     */
    private function mayProcessNewOrNonCompleted():bool
    {
        return ($this->handled === self::SOURCE_NEW) || ($this->handled === self::SOURCE_NON_COMPLETED) && $this->status === self::CONTINUE;
    }

}