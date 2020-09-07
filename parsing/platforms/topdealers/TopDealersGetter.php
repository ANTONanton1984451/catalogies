<?php


namespace parsing\platforms\topdealers;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\GetterInterface;
use parsing\logger\LoggerManager;
use Unirest\Request;

/**
 * Class TopDealersGetter
 * @package parsing\platforms\topdealers
 *
 */

class TopDealersGetter implements GetterInterface
{
   private const RESPONSES_URN  = 'responses/';
   private const MAIN_URL       = 'https://topdealers.ru';
   private const BAD_CONNECTION = '-666';                   //ответ при отсутствии соединения с донором

   private $database;

    private $config;
    private $source;
    private $handled;
    private $hash;

    private $iterator = 0;
    private $half_Year_Ago;

    private $MainPage;
    private $ResponsesPage;

    private $last_date_review_db;

    private $mainData;


    public function __construct(DatabaseShell $database)
    {
        $this->half_Year_Ago = time() - self::HALF_YEAR_TIMESTAMP;
        $this->database = $database;
    }

    /**
     * @return int|array
     * Метод  получает отзывы и в зависимости от флага handled применяет разные алгоритмы для получения отзывов
     */
    public function getNextRecords()
    {
        $this->iterator++;
        $this->checkIteration();
        $this->MainPage = $this->getContent($this->source);

        if($this->MainPage !== self::BAD_CONNECTION){
            $this->HTML_to_DOM($this->MainPage);
        }else{
            $this->mainData = self::END_CODE;

            LoggerManager::log(LoggerManager::ERROR,
                                    'Can not connect to site|TopdealersGetter',
                                            ['source'=>$this->source]);
            $this->database->updateSourceReview($this->hash, ['handled'=>'UNPROCESSABLE']);
        }

        if($this->handled == self::SOURCE_NEW && $this->mainData !== self::END_CODE){
            $this->formMainData($this->half_Year_Ago);
        }elseif ($this->handled == self::SOURCE_HANDLED && $this->mainData !== self::END_CODE){
            $this->parseConfig();
            $this->formMainData($this->last_date_review_db);
        }

        \phpQuery::unloadDocuments();
        return $this->mainData;
    }

    /**
     * @param $config
     * Установка конфигов,необходимых для парсинга
     */
    public function setConfig($config)
    {
        $this->source  = $config['source'];
        $this->handled = $config['handled'];

        $this->config  = json_decode($config['config'],true);
        $this->hash = $config['source_hash'];

    }

    private  function formMainData(int $timeToCut):void
    {
        $this->setMetaInfo();
        $this->setReviews($timeToCut);
        $this->setSourceConfig($timeToCut);

    }

    /**
     * Установка в буфер даты последнего отзыва
     */
    private function parseConfig():void
    {
        $this->last_date_review_db = $this->config['last_review_time'];
    }

    /**
     * @param string $url
     * @return string|null
     * Получение нужной страницы,в случае успешного подключения.
     * В противном случае
     */
    private function getContent(string $url):?string
    {
        if($this->checkURL($url)){
            return Request::get($url)->body;
        }else{
            return self::BAD_CONNECTION;
        }
    }

    /**
     * @param int $defaultDate
     * Установка конфигов доты,в случае,если отзывов нет,то устанавливается дефолтное значение
     */
    private function setSourceConfig(int $defaultDate):void
    {
        if(!empty($this->mainData['reviews'])){
            $this->mainData['config']['last_review_time'] = $this->mainData['reviews'][0]['date'];
        }else{
            $this->mainData['config']['last_review_time'] = $defaultDate;
        }

    }

    /**
     * Установка мета-информации
     */
    private function setMetaInfo():void
    {
        $rating = $this->MainPage->find('.overall-rating tbody td');
        $arrToJson=[];
        $i=0;

        foreach ($rating as $v) {

            if (pq($v)->attr('class') == 'category first') {
                $arrToJson[$i]['name'] = pq($v)->text();
            } elseif (pq($v)->attr('class') == 'rating2') {
                $arrToJson[$i]['value'] = pq($v)->text();
                $i++;
            }

        }

        $this->mainData['meta_info'] = $arrToJson;
    }


    /**
     * @param int $date
     * Основной метод Геттера.
     * Собирает отзывы до указанной даты и кладёт их в переменную $mainData
     */
    private function setReviews(int $date):void
    {
        $this->ResponsesPage = $this->getContent($this->source . self::RESPONSES_URN);

        if($this->ResponsesPage === self::BAD_CONNECTION){
            $this->mainData = self::END_CODE;
        }else{
            $this->HTML_to_DOM($this->ResponsesPage);
            $this->parseReviews();
            $this->cutReviewsToTime($date);
        }

    }

    /**
     * С помощью phpQuery парсим отзывы.
     * В случае,если отзыв неполный,то применяется доп.метод,который подбирает новый отзыв
     */
    private function parseReviews():void
    {
        $responses=$this->ResponsesPage->find('div[id^="resp"] .info');                         //находим карточку товара

        foreach ($responses as $v){                                                             //разбираем эту карточку

            $responseFullInfo['tonal'] = trim(pq($v)->find('.comment-type')->text());  //тональность оценки
            $responseFullInfo['title'] = pq($v)->find('.title')->text();               //заголовок отзыва
            $responseFullInfo['identifier'] = pq($v)->find('.name')->text();           //имя юзера

            $iterator = 1;

            foreach (pq($v)->find('.date-list dd') as $value){                         //дата отзыва
                if($iterator%2 == 0){
                    $responseFullInfo['date']=strtotime(pq($value)->text());
                }
                $iterator++;
            }
            if(pq($v)->find('p[class="read_all"]')->text()){//если ответ не полный,то редирект на страницу полного отзыва

                $fullReviewUrl = self::MAIN_URL . pq($v)->find('p[class="read_all"] a')->attr('href');
                $responseFullInfo["text"]=$this->getFullReview($fullReviewUrl);

            }else{
                $responseFullInfo["text"] = pq($v)->find('p')->text();
            }

            $this->mainData['reviews'][] = $responseFullInfo;
        }
    }

    /**
     * @param string $url
     * @return string
     * По юрл забирает полный отзыв пользователя
     */
    private function getFullReview(string $url):string
    {
        $FullResponsePage = $this->getContent($url);

        if($FullResponsePage !== self::BAD_CONNECTION){
            $this->HTML_to_DOM($FullResponsePage);
            return $FullResponsePage->find('.info p')->text();
        }
        return 'Нет полной страницы';
    }

    /**
     * @param int $timeBreak
     * Обрезает отзывы до указанной временной метки
     */
    private function cutReviewsToTime(int $timeBreak):void
    {
        $data = $this->mainData['reviews'];

        for($i=0; $i<count($data); $i++){
            if($data[$i]['date'] > $timeBreak ){
                continue;
            }else{
                $data=array_slice($data,0,$i);
                $this->mainData['reviews'] = $data;
                break;
            }

        }

    }

    /**
     * @param $html
     * преобразовывает HTML документ в объект phpQuery
     */
    private function HTML_to_DOM(&$html):void
    {
        $html = \phpQuery::newDocument($html);
    }

    /**
     * Проверяет итерацию,если итераций в цикле больше одной-заканчивает работу геттера
     */
    private function checkIteration():void
    {
        if($this->iterator !== 1){
            $this->mainData = self::END_CODE;
        }
    }

    /**
     * @param string $url
     * @return bool
     * Проверяет URL на ответ сервера
     */
    private function checkURL(string $url):bool
    {
    $response = get_headers($url);

    if($response[0] === 'HTTP/1.1 200 OK'){
        return true;
    }else{
        return false;
    }

}
}