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
    const LAST_ITERATION = 'last_iteration';


    protected $source;
    protected $track;
    protected $handle;

    private $client;
    private $curl;
    private $halfYearAgo;
    private $trigger;
    private $iterator = 0;
    private $nextPageToken = false;
    private $last_review_date;
    private $last_review_hash;

    private $config;
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

        $client->setAuthConfig(__DIR__.'\secret.json');

        $this->halfYearAgo=time()-self::HALF_YEAR;

    }


    /**
     *
     * Функция,которая вызывает все остальные методы,получает массив данных с GMB_API в необработанном виде
     * При обнаружении отсутсвия отзывов или того,что уже совершилась последняя итерация,сразу же возвращает массив
     * с триггером,сообщающем о конце работы цикла.
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

        if ($this->trigger == self::LAST_ITERATION) {
                $this->mainData = self::END_CODE;
        }else{
                $this->refreshToken();
                $this->connectToPlatform();
                $this->checkResponse();
        }

        if($this->handle == 'NEW' && $this->mainData !== self::END_CODE){

                $this->setLastReviewConfig();
                $this->cutToTime($this->halfYearAgo);

                $cuttedData = $this->mainData['platform_info']['reviews'];

               if(empty($cuttedData)){
                    $this->mainData = self::END_CODE;
               }


        }elseif($this->handle == 'HANDLED' && $this->mainData != self::END_CODE){

                $lastReviewFromGMB = $this->arrayReviewsToMd5Hash();

                if($lastReviewFromGMB === $this->config['last_review_hash']){
                    $this->mainData = self::END_CODE;
                }else{
                    $this->setLastReviewConfig();
                    $this->cutToTime($this->last_review_db);
                }

        }

        return $this->mainData;
    }

    /**
     * Метод проверяет ответ от GMB_API.
     * В случае,если нет отзывов,то мы оставляем сообщение о конце работы
     */
    private function checkResponse():void
    {
        if(empty($this->mainData['platform_info']['reviews'])){

            $this->mainData = self::END_CODE;

        }elseif (!empty($this->mainData['platform_info']['nextPageToken'])){

            $this->nextPageToken = $this->mainData['platform_info']['nextPageToken'];

        }else{

            $this->trigger = self::LAST_ITERATION;

        }
    }

    /**
     * @param $config
     * Небольшое дополнение метода родительского класса,не влияющее на работу всех классов
     */
    public function setConfig($config)
    {
        $this->source = $config['source'];
        $this->handle = $config['handle'];
        $this->mainData['config'] = $config['config'];

        if(@isset($config['config']['last_review_date'])){
            $this->last_review_db = $config['config']['last_review_date'];
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
        if($this->iterator === 1){
            $lastReview = $this->mainData['platform_info']['reviews'][0];
            $this->last_review_hash = $this->arrayReviewsToMd5Hash();
            $this->last_review_date = strtotime($lastReview['updateTime']);

            $this->mainData['config']['last_review_date'] = $this->last_review_date;
            $this->mainData['config']['last_review_hash'] = $this->last_review_hash;
        }
    }


    /**
     * Обновляет токен и перезаписвает конфиги,записывая туда обновлённый токен токен
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

            for($i=0;$i<count($data);$i++){

                $timeStamp=strtotime($data[$i]['updateTime']);

                if($timeStamp > $timeBreak ){
                    continue;
                }else{
                    $data=array_slice($data,0,$i);
                    $this->trigger = self::LAST_ITERATION;
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
        if(!$this->nextPageToken){
            $request_url = self::URL.$this->source.'/reviews?pageSize='.self::PAGE_SIZE;
        }else{
            $request_url = self::URL.$this->source.'/reviews?pageSize='.self::PAGE_SIZE.'&pageToken='.$this->nextPageToken;
        }

        $this->curl = curl_init($request_url);

        $token_type = $this->mainData['config']['token_info']['token_type'];
        $access_token = $this->mainData['config']['token_info']['access_token'];

        $header_str = 'Authorization: '.$token_type.' '.$access_token;

        curl_setopt($this->curl,CURLOPT_HTTPHEADER,[$header_str]);
        curl_setopt($this->curl,CURLOPT_RETURNTRANSFER,true);

        $response = curl_exec($this->curl);

        $this->mainData['platform_info'] = json_decode($response,true);
    }

    /**
     * @return string
     * Переводит массив в хэш строку
     */
    private function arrayReviewsToMd5Hash():?string
    {
        if($this->mainData !== self::END_CODE){

            $lastUpdateReview = $this->mainData['platform_info']['reviews'][0];
            $implode_array = implode($lastUpdateReview,'');

            return md5($implode_array);
        }
    }
}