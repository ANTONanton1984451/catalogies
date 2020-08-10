<?php


namespace parsing\platforms\topdealers;


use parsing\factories\factory_interfaces\GetterInterface;

class TopDealersGetter implements GetterInterface
{
    const RESPONSES_URN  = 'responses/';
    const END_CODE       = 42;
    const EMPTY          = -666;
    const MAIN_URL       = 'https://topdealers.ru';
    const BAD_CONNECTION = '-666';
    const HALF_YEAR      = 15552000;
    const FAKE_HASH      = 'abc';


    private $config;
    private $source;
    private $handled;

    private $iterator      = 0;
    private $half_Year_Ago;

    private $MainPage;
    private $ResponsesPage;
    private $FullResponsePage;

    private $trigger = 1;
    private $last_date_review_db;
    private $mainData;

    public function __construct()
    {
        $this->half_Year_Ago = time() - self::HALF_YEAR;
    }

    public function getNextReviews()
    {
        $this->checkIteration();
        $this->MainPage = $this->getContent($this->source);

        if($this->MainPage !== self::BAD_CONNECTION){
            $this->HTML_to_DOM($this->MainPage);
        }else{
            $this->mainData = self::END_CODE;
        }

        if($this->handled == 'NEW' && $this->mainData !== self::END_CODE){

            $this->setMetaInfo();
            $this->setReviews($this->half_Year_Ago);
            $this->setSourceConfig($this->half_Year_Ago);

        }elseif ($this->handled == 'HANDLE' && $this->mainData !== self::END_CODE){

            $this->setMetaInfo();
            $this->parseConfig();
            $this->setReviews($this->last_date_review_db);
            $this->setSourceConfig($this->last_date_review_db);

        }

        return $this->mainData;
    }


    public function setConfig($config)
    {
        $this->source  = $config['source'];
        $this->handled = $config['handled'];
        $this->config  = $config['config'];
    }

    private function parseConfig():void
    {
        $this->last_date_review_db = $this->config['last_review_date'];
    }


    private function getContent(string $url):?string
    {
        if($this->checkURL($url)){
            return file_get_contents($url);
        }else{
            return self::BAD_CONNECTION;
        }
    }

    private function setSourceConfig(int $defaultDate):void
    {
        if(!empty($this->mainData['reviews'])){
            $this->mainData['config']['last_review_time'] = $this->mainData['reviews'][0]['date'];
        }else{
            $this->mainData['config']['last_review_time'] = $defaultDate;
        }

    }





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


    private function parseReviews():void
    {
        $responses=$this->ResponsesPage->find('div[id^="resp"] .info');       //находим карточку товара

        foreach ($responses as $v){//разбираем эту карточку

            $responseFullInfo['tonal'] = trim(pq($v)->find('.comment-type')->text());  //тональность оценки
            $responseFullInfo['title'] = pq($v)->find('.title')->text();                      //заголовок отзыва
            $responseFullInfo['identifier'] = pq($v)->find('.name')->text();                  //имя юзера

            $iterator = 1;
            foreach (pq($v)->find('.date-list dd') as $value){                                  //дата отзыва
                if($iterator%2 == 0){
                    $responseFullInfo['date']=strtotime(pq($value)->text());
                }
                $iterator++;
            }
            if(pq($v)->find('p[class="read_all"]')->text()){//если ответ не полный,то редирект на страницу полного отзыва

                $fullReviewUrl = self::MAIN_URL.pq($v)->find('p[class="read_all"] a')->attr('href');
                $responseFullInfo["text"]=$this->getFullReview($fullReviewUrl);

            }else{
                $responseFullInfo["text"] = pq($v)->find('p')->text();
            }

            $this->mainData['reviews'][] = $responseFullInfo;
        }
    }


    private function getFullReview(string $url):string
    {
        $this->FullResponsePage = $this->getContent($url);
        $this->HTML_to_DOM($this->FullResponsePage);

        return $this->FullResponsePage->find('.info p')->text();
    }


    private function cutReviewsToTime(int $timeBreak):void
    {
        $data = $this->mainData['reviews'];

        for($i=0; $i<count($data); $i++){
            if($data[$i]['date'] > $timeBreak ){
                continue;
            }else{
                $data=array_slice($data,0,$i);
                $this->mainData['reviews'] = $data;
                return;
            }

        }

    }

    private function HTML_to_DOM(&$html):void
    {
        $html = \phpQuery::newDocument($html);
    }


    private function checkIteration():void
    {
        $this->iterator++;

        if($this->iterator !== 1){
            $this->mainData = self::END_CODE;
        }
    }

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