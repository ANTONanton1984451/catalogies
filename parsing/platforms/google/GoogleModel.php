<?php


namespace parsing\platforms\google;


use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;

class GoogleModel implements ModelInterface
{
    const LAST_ITERATION = 'last_iteration';
    const HANDLED = 'HANDLED';
    const NEW = 'NEW';
    const ONE_DAY_IN_SEC = 86400;
    const ONE_HOUR_SEC = 3600;
    const BALANCE_COEFFICIENT = 4;

    private $dataBase;
    private $constInfo;
    private $handled;
    private $tempReviews;
    private $reviewCount = 0;

    public function __construct(DatabaseShell $dbShell)
    {
        $this->dataBase = $dbShell;
        $this->constInfo['platform'] = 'google';
    }

    public function writeData($data)
    {
       if($this->handled === self::NEW){
           $this->writeNewData($data);
       }else{
           $this->writeHandledData($data);
       }

    }


    public function setConfig($config)
    {
        $this->constInfo['source_hash_key'] = $config['source_hash'];
        $this->handled = $config['handled'];
    }

    private function writeHandledData(array $data):void
    {
        if($data['status'] === self::LAST_ITERATION){
            $this->updateMetaInfo($data['meta_info']);
            $parse_date_hours = round(time()/self::ONE_HOUR_SEC);
            $this->dataBase->updateTaskQueue(['last_parse_date'=>$parse_date_hours],["source_hash_key"=>$this->constInfo['source_hash_key']]);
        }else{

            $this->insertReviews($data['reviews']);
            $this->updateConfig($data['config']);
        }
    }

    private function writeNewData(array $data):void
    {
        if($data['status'] === self::LAST_ITERATION){
            $this->updateMetaInfo($data['meta_info']);
            $this->dataBase->updateSourceReview($this->constInfo['source_hash_key'],['handled'=>'HANDLED']);
            $coefficients = $this->calcCoefs();
            $this->dataBase->insertTaskQueue(array_merge($coefficients,["source_hash_key"=>$this->constInfo['source_hash_key']]));
            //todo::Сделать обновление в source_review
            //todo::Сделать метод для записи в очередь
            //todo::Сделать сервич расчёта коэфициентов
        }else{
            $this->tempReviews = $data['reviews'];
            $this->reviewCount += count($data['reviews']);
            $this->insertReviews($data['reviews']);
            $this->updateConfig($data['config']);
        }
    }


    private function calcCoefs():array
    {

        $last_review_date_sec = $this->tempReviews[count($this->tempReviews)-1]['date'];
        $last_review_date_day = (time() - $last_review_date_sec)/self::ONE_DAY_IN_SEC;
        $last_review_date_day = round($last_review_date_day);

        $review_per_day = $this->reviewCount/$last_review_date_day;

        if ($review_per_day > 6) {
            $review_per_day = 6 * self::BALANCE_COEFFICIENT;
        } elseif ($review_per_day < 1) {
            $review_per_day = 1 * self::BALANCE_COEFFICIENT;
        } else {
            $review_per_day = round($review_per_day) * self::BALANCE_COEFFICIENT;
        }

        $last_parse_date = round(time()/self::ONE_HOUR_SEC);
        $a = 0;
        return [
            'last_parse_date'=>$last_parse_date,
            'review_per_day'=>$review_per_day
        ];
    }

    private function insertReviews(?array $reviews):void
    {
        if(!empty($reviews)){
            $this->dataBase->insertReviews($reviews,$this->constInfo);
        }
    }

    private function updateMetaInfo(string $meta):void
    {
        $this->dataBase->updateSourceReview(
                                            $this->constInfo['source_hash_key'],
                                            ['source_meta_info'=>$meta]
                                            );
    }


    private function updateConfig(array $config):void
    {
        $config = json_encode($config);
        $this->dataBase->updateSourceReview(
                                            $this->constInfo['source_hash_key'],
                                            ['source_config'=>$config]
                                            );
    }
}