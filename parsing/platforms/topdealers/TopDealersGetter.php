<?php


namespace parsing\platforms\topdealers;

use DiDom\Document;
use DiDom\Element;
use DiDom\Query;
use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\GetterInterface;
use parsing\logger\LoggerManager;
use Unirest\Request;
use Symfony\Component\DomCrawler\Crawler;

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
    /**
     * @var DatabaseShell
     */
   private $database;

    /**
     * @var array
     * конфиги из БД
     */
    private $config;

    /**
     * @var string
     * Ссылка на карточку магазина
     */
    private $source;

    /**
     * @var string
     * флаг из БД
     */
    private $handled;

    /**
     * @var string
     * Хэш сурса из БД
     */
    private $hash;

    /**
     * @var int
     * Количество выполнений операции getNextRecords
     */
    private $iterator = 0;

    /**
     * @var int
     * Пол года назад в секнудах
     */
    private $half_Year_Ago;

    /**
     * @var string|Document
     * Главная старица магазина на сайте
     */
    private $MainPage;

    /**
     * @var string|Document
     * Страница с отзывами
     */
    private $ResponsesPage;

    /**
     * @var int
     * Дата последнего отзыва из БД
     */
    private $last_date_review_db;

    /**
     * @var array|int
     */
    private $mainData;

    /**
     * TopDealersGetter constructor.
     * @param DatabaseShell $database
     */
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
        if($this->iterator !== 1){
            $this->mainData = self::END_CODE;
        }

        $this->MainPage = $this->getContent($this->source);

        if($this->MainPage !== self::BAD_CONNECTION){
            $this->HTML_to_DOM($this->MainPage);
        }else{
            $this->handleCrashLink();
        }

        if($this->isNewOrNonCompleted() && $this->mainData !== self::END_CODE){

            $this->formMainData($this->half_Year_Ago);

        }elseif ($this->isHandleOrNonUpdated() && $this->mainData !== self::END_CODE){
            $this->parseConfig();
            $this->formMainData($this->last_date_review_db);
        }

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

    /**
     * Действия для сломанной ссылки
     * Выставление нужного флага ссылке
     */
    private function handleCrashLink():void
    {
        $this->mainData = self::END_CODE;
        $handled = self::SOURCE_UNPROCESSABLE;

        if($this->handled === self::SOURCE_NEW){
            $handled = self::SOURCE_NON_COMPLETED;
        }elseif ($this->handled === self::SOURCE_HANDLED){
            $handled = self::SOURCE_NON_UPDATED;
        }
        $this->database->updateSourceReview($this->hash, ['handled'=>$handled]);
    }

    /**
     * @param int $timeToCut
     * Метод формирует данные для отправки,принимает время для отсеивания отзывов
     */
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
     * В противном случае идёт запись в лог
     */
    private function getContent(string $url):?string
    {
        if($this->checkURL($url)){
            return Request::get($url)->body;
        }else{
            LoggerManager::log(LoggerManager::ERROR,
                'Can not connect to site|TopdealersGetter',
                        ['source'=>$url]);
            return self::BAD_CONNECTION;
        }
    }

    /**
     * @param int $defaultDate
     * Установка конфигов даты,в случае,если отзывов нет,то устанавливается дефолтное значение
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
        $ratingElement = $this->MainPage->find('.overall-rating tbody td');
        $arrToJson=[];
        $i=0;

        foreach ($ratingElement as $v){
            $className = $v->getNode()->getAttribute('class');
            $value = $v->getNode()->nodeValue;

            if ($className == 'category first') {
                $arrToJson[$i]['name'] = $value;
            } elseif ($className == 'rating2') {
                $arrToJson[$i]['value'] = $value;
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
     * С помощью DiDom парсим отзывы.
     * В случае,если отзыв неполный,то применяется доп.метод,который подбирает новый отзыв
     * Селекторы которые используются :
     * 1.'div[id^="resp"] .info' - карточка отзывов
     * 2.'.comment-type' - тональность отзыва
     * 3.'.title'-заголовок отзыва
     * 4.'.name'-имя юзера
     * 5.'.date-list dd'-дата отзыва
     * 6.'p[class="read_all"]'-селектор для обзаца "читать далее"
     * 7.'p[class="read_all"] a' - ссылка на полный отзыв
     */
    private function parseReviews():void
    {
        $responsesCard=$this->ResponsesPage->find('div[id^="resp"] .info');

        foreach ($responsesCard as $v){
            $response['tonal'] = trim($v->find('.comment-type')[0]->text());
            $response['title'] = trim($v->find('.title')[0]->text());
            $response['identifier'] = trim($v->find('.name')[0]->text());
            $iterator = 1;

            foreach ($v->find('.date-list dd') as $value){
                if($iterator%2 == 0){
                    $response['date']=strtotime($value->text());
                }
                $iterator++;
            }

            $test = $v->find('p[class="read_all"]');
            if($test){

                $additional_url = $test[0]->find('p[class="read_all"] a')[0]->attr('href');
                $fullReviewUrl = self::MAIN_URL . $additional_url;

                $response['text'] = $this->getFullReview($fullReviewUrl);
            }else{
                $response['text'] = $v->find('p')[0]->text();
            }

            $this->mainData['reviews'][] = $response;

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
            return $FullResponsePage->find('.info p')[0]->text();
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
     * по ссылке
     */
    private function HTML_to_DOM(&$html):void
    {
        $html = new Document($html);
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

    /**
     * @return bool
     * Метод нужен для сокращения кода в методе GetNextRecords
     */
    private function isHandleOrNonUpdated():bool
    {
        return ($this->handled === self::SOURCE_HANDLED) || ($this->handled === self::SOURCE_NON_UPDATED);
    }

    /**
     * @return bool
     * Метод нужен для сокращения кода в методе GetNextRecords
     */
    private function isNewOrNonCompleted():bool
    {
        return ($this->handled === self::SOURCE_NEW) || ($this->handled === self::SOURCE_NON_COMPLETED);
    }


}