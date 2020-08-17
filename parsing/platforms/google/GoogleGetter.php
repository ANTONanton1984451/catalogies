<?php
// todo: reviews можно вынести в константу
// todo: rename toHash in arrayReviewsToMd5Hash
// todo: свести к одному return'y
// todo: first iterate -> set something

namespace parsing\platforms\google;

use parsing\factories\factory_interfaces\GetterInterface;
use parsing\platforms\Getter;
use Google_Client;

/**
 * Class GoogleGetter
 * @package parsing\platforms\google
 */
class GoogleGetter extends Getter implements GetterInterface
{
    const URL = 'https://mybusiness.googleapis.com/v4/';
    const PAGE_SIZE = '50';
    const HALF_YEAR = 15552000;
    

    private $client;
    private $curl;
    private $halfYearAgo;
    private $trigger;
    private $iterator = 0;
    private $nextPageToken = false;
    private $last_review_date;
    private $last_review_hash;

    private $config;

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
     *
     */
    public function getNextRecords()
    {
        $this->iterator++;

        if ($this->trigger == 'last_iteration') {
            return self::END_CODE;
        }

        $this->refreshToken();

        $google_request = $this->connectToPlatform();


        if(empty($google_request['reviews'])){

            return self::END_CODE;

        }elseif (!empty($google_request['nextPageToken'])){

            $this->nextPageToken = $google_request['nextPageToken'];

        }else{

            $this->trigger = 'last_iteration';
            // todo: Можно запихнуть в константу
        }


        if($this->handle == 'NEW'){
            // todo: Определиться с форматом хранения handled

                $this->firstIterate($google_request['reviews'][0]);

                $this->config['last_review_date'] = $this->last_review_date;
                $this->config['last_review_hash'] = $this->last_review_hash;

               $google_request['reviews'] = $this->cutToTime($google_request['reviews'],$this->halfYearAgo);

               if(!$google_request['reviews']){
                    return self::END_CODE;
               }


        }elseif($this->handle == 'HANDLED'){

                $lastReviewFromGMB = $this->toHash($google_request['reviews'][0]);

                if($lastReviewFromGMB === $this->config['last_review_hash']){

                    return self::END_CODE;
                }
                $this->firstIterate($google_request['reviews'][0]);

                $google_request['reviews'] = $this->cutToTime($google_request['reviews'],$this->last_review_db);

                if(!$google_request['reviews']){
                    return self::END_CODE;
                }

                $this->config['last_review_date'] = $this->last_review_date;
                $this->config['last_review_hash'] = $this->last_review_hash;
        }

        return [
            'platform_info'=>$google_request,
            'config'=>$this->config,
            'trigger'=>$this->trigger
        ];

    }

    /**
     * @param $config
     * Небольшое дополнение метода родительского класса,не влияющее на работу всех классов
     */
    public function setConfig($config)
    {
        $this->source = $config['source'];
        $this->handle = $config['handle'];
        $this->config = $config['config'];
        if(isset($config['config']['last_review_date'])){
            $this->last_review_db = $config['config']['last_review_date'];
        }
    }


    public function getNotifications()
    {
        // TODO: Implement getNotifications() method.
    }

    /**
     * @param array $lastReview
     * Если происходит первая итерация цикла,то метод превращает массив в хэш-строку
     * для записи в специальную переменную
     * и записывает дату самого последнего по времени отзыва.
     */
    private function firstIterate(array $lastReview):void
    {
        if($this->iterator === 1){
            $this->last_review_hash = $this->toHash($lastReview);
            $this->last_review_date = strtotime($lastReview['updateTime']);
        }
    }


    /**
     * Обновляет токен и перезаписвает конфиги,записывая туда обновлённый токен токен
     */
    private function refreshToken():void
    {

        $this->client->setAccessToken($this->config['token_info']);

        if($this->client->isAccessTokenExpired()){

            $refreshToken = $this->client->getRefreshToken();
            $this->config['token_info'] = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);

        }
    }

    /**
     * @param array|null $data-ссылка на массив данных,которые нужно проверить на актуальность по дате
     * @param int $timeBreak-дата,которая  используется при проверке.
     * @return array
     * Функция обрезает данные до установленной даты.
     * В случае достижения заданной даны,меняет триггер на last_iteration
     */
    private function cutToTime(?array $data, int $timeBreak):array
    {

        for($i=0;$i<count($data);$i++){

            $timeStamp=strtotime($data[$i]['updateTime']);

            if($timeStamp > $timeBreak ){
                continue;
            }else{
                $data=array_slice($data,0,$i);
                $this->trigger = 'last_iteration';
                return $data;
            }
        }
        return $data;

    }

    /**
     * @return array|null
     * Метод подключается к сервисам гугл по данному $source.
     * Если имеется nextPageToken,то он используется,также формируется заголовок запроса с нужным токеном
     * Возвращает декодированный ответ от GMB_API
     */
    private function connectToPlatform():?array
    {
        if(!$this->nextPageToken){
            $request_url = self::URL.$this->source.'/reviews?pageSize='.self::PAGE_SIZE;
        }else{
            $request_url = self::URL.$this->source.'/reviews?pageSize='.self::PAGE_SIZE.'&pageToken='.$this->nextPageToken;
        }

        $this->curl = curl_init($request_url);

        $header_str = 'Authorization: '.$this->config['token_info']['token_type'].' '.$this->config['token_info']['access_token'];

        curl_setopt($this->curl,CURLOPT_HTTPHEADER,[$header_str]);
        curl_setopt($this->curl,CURLOPT_RETURNTRANSFER,true);

        $response = curl_exec($this->curl);

        return json_decode($response,true);
    }

    /**
     * @param array $review_array_row
     * @return string
     * Переводит массив в хэш строку
     */
    private function toHash(array $review_array_row):string
    {
        $implode_array = implode($review_array_row,'');
        return md5($implode_array);
    }
}