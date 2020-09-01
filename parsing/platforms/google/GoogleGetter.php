<?php


namespace parsing\platforms\google;

use parsing\factories\factory_interfaces\GetterInterface;
use Google_Client;

/**
 * Class GoogleGetter
 * @package parsing\platforms\google
 */
class GoogleGetter  implements GetterInterface
{
    const URL = 'https://mybusiness.googleapis.com/v4/';
    const PAGE_SIZE = '50';
    const HALF_YEAR = 15552000;
    const CONTINUE = 777;
    const LAST_ITERATION = 'last_iteration';

    protected $source;
    protected $track;//maybe deleted?
    protected $handle;

    private $trigger = self::CONTINUE;
    private $client;
    private $halfYearAgo;
    private $iterator = 0;

    private $mainData;

    /**
     * @var int
     * Дата последнего отзыва,лежащего в БД.
     */
    private $last_review_db;

    /**
     * GoogleGetter constructor.
     * @param Google_Client $client
     * @throws \Google_Exception
     * установка переменной,отсчитывающей временную метку в полгода
     */
    public function __construct(Google_Client $client)
    {
        $this->client = $client;

        $client->setAuthConfig(__DIR__.'/secret.json');

        $this->halfYearAgo=time()-self::HALF_YEAR;

    }

    /**
     *
     * Функция,которая вызывает все остальные методы,получает массив данных с GMB_API в необработанном виде
     * При обнаружении отсутсвия отзывов или того,что уже совершилась последняя итерация,сразу же возвращает
     * триггер,сообщающий о конце работы цикла.
     * При первичной обработке происходит получение всех отзывов за последние пол-года.
     * При вторичной обработке происходит сверка хэшей конфига и первого отзыва,
     * полученного в момент выполнения через GMB_API.
     * Сначала метод выполняет общие действия для обоих видов обработки,затем происходит разделение методов в зависимости
     * от флага $handled.
     *
     */
    public function getNextRecords()
    {
        $this->iterator++;

        if ($this->trigger === self::END_CODE) {
               $this->mainData = self::END_CODE;
        }elseif($this->trigger === self::LAST_ITERATION){
            $this->mainData['platform_info']['reviews'] = [];
            $this->trigger = self::END_CODE;
        }else{
                $this->refreshToken();
                $this->connectToPlatform();
                $this->checkResponse();
        }

        if($this->handle === self::STATUS_NEW && $this->trigger === self::CONTINUE){
            $this->formData($this->halfYearAgo);

        }elseif($this->handle == self::STATUS_HANDLED && $this->trigger === self::CONTINUE){
            $lastReviewFromSource = $this->arrayReviewToMd5Hash();

            if($lastReviewFromSource === $this->mainData['config']['last_review_hash']){
                $this->mainData['platform_info']['reviews'] = [];
                $this->trigger = self::END_CODE;
            }else{
                $this->formData($this->last_review_db);
            }
        }

        return $this->mainData;
    }


    private function formData(int $timeToCut):void
    {
        if($this->iterator === 1){
            $this->setLastReviewConfig();
        }
        $this->cutToTime($timeToCut);
        $this->checkMainData();
    }
    /**
     * Метод проверяет ответ от GMB_API.
     * В случае,если нет отзывов,то мы оставляем сообщение о конце работы
     */
    private function checkResponse():void
    {
        if (empty($this->mainData['platform_info']['reviews'])){
            $this->trigger = self::END_CODE;
        }
    }


    private function checkMainData():void {
        if(empty($this->mainData['platform_info']['reviews'])){
            $this->trigger = self::END_CODE;
        }
        if(empty($this->mainData['platform_info']['nextPageToken'])){
            $this->trigger = self::LAST_ITERATION;
        }
    }

    /**
     * @param $config
     * Парсинг коннфигов:выставление $handled,$source и конфигов ссылки
     */
    public function setConfig($config)
    {
        $decode_config = json_decode($config['config'],true);

        $this->source = $config['source'];
        $this->handle = $config['handled'];
        $this->mainData['config'] = $decode_config;

        if($this->handle === self::STATUS_HANDLED){
            $this->last_review_db = $decode_config['last_review_date'];
        }

    }


    public function getNotifications()
    {
        // TODO: Implement getNotifications() method.
    }

    /**
     * Если происходит первая итерация цикла,то метод превращает массив в хэш-строку
     * для записи в специальную переменную
     * и записывает дату самого последнего по времени отзыва.
     */
    private function setLastReviewConfig():void
    {
            $lastReview = $this->mainData['platform_info']['reviews'][0];
            $this->mainData['config']['last_review_date'] = strtotime($lastReview['updateTime']);
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
            $this->mainData['config']['token_info'] = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
        }
    }

    /**
     * @param int $timeBreak-дата,которая  используется при проверке.
     * Функция обрезает данные до установленной даты.
     * В случае достижения заданной даны,меняет триггер на last_iteration
     */
    private function cutToTime(int $timeBreak):void
    {
        $data = $this->mainData['platform_info']['reviews'];

        for($i=0;$i<count($data)-1;$i++){

            $timeStamp=strtotime($data[$i]['updateTime']);

            if($timeStamp <= $timeBreak ){
                $data=array_slice($data,0,$i);
                $this->mainData['platform_info']['reviews'] = $data;
                return;
            }
        }
    }

    /**
     *
     * Метод подключается к сервисам гугл по данному $source.
     * Если имеется nextPageToken,то он используется,также формируется заголовок запроса с нужным токеном
     * Возвращает декодированный ответ от GMB_API
     */
    private function connectToPlatform():void
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
     * Переводит массив в хэш строку
     */
    private function arrayReviewToMd5Hash(): string
    {
            $lastUpdateReview = $this->mainData['platform_info']['reviews'][0];
            $implode_array = implode($lastUpdateReview,'');
            return md5($implode_array);
    }

}