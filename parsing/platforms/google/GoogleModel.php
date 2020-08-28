<?php


namespace parsing\platforms\google;


use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;

class GoogleModel implements ModelInterface
{
    const HANDLED = 'HANDLED';
    const NEW = 'NEW';
    const ONE_DAY_IN_SEC = 86400;
    const ONE_HOUR_SEC = 3600;
    const BALANCE_COEFFICIENT = 4;
    const PLATFORM = 'google';

    private $dataBase;
    private $handled;
    private $tempReviews;
    private $reviewCount = 0;
    private $source_hash;

    public function __construct(DatabaseShell $dbShell)
    {
        $this->dataBase = $dbShell;

    }

    public function writeData($data)
    {
       if($this->handled === self::NEW){
           $this->writeNewData($data);
       }elseif($this->handled === self::HANDLED){
           $this->writeHandledData($data);
       }

    }


    public function setConfig($config)
    {
        $this->source_hash = $config['source_hash'];
        $this->handled = $config['handled'];
    }

    private function writeHandledData(array $data):void
    {

        if(!empty($data['reviews'])){
            $this->insertReviews($data['reviews']);
            $this->updateConfig($data['config']);
        }else{
            $columns = ['source_meta_info'=>json_encode($data['meta'])];
            $this->dataBase->updateSourceReview($this->source_hash,$columns);
            $parse_date_hours = round(time()/self::ONE_HOUR_SEC);
            $this->dataBase->updateTaskQueue($this->source_hash,['last_parse_date'=>$parse_date_hours]);
        }
    }

    private function writeNewData(array $data):void
    {
        if(!empty($data['reviews'])){
            $this->tempReviews = $data['reviews'];//????
            $this->reviewCount += count($data['reviews']);
            $this->insertReviews($data['reviews']);
            $this->updateConfig($data['config']);

        }else{
            $columns = [
                    'source_meta_info' => json_encode($data['meta']),
                    'handled'=> self::HANDLED
                    ];
            $this->dataBase->updateSourceReview($this->source_hash,$columns);
            $coefficients = $this->calcCoefs();
            $this->dataBase->insertTaskQueue(array_merge($coefficients,["source_hash_key"=>$this->source_hash]));

            //todo::Сделать сервис расчёта коэфициентов
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

    private function insertReviews(array $reviews):void
    {
        $constInfo = [
                        'source_hash_key'=>$this->source_hash,
                        'platform'=>self::PLATFORM
                     ];

        $this->dataBase->insertReviews($reviews,$constInfo);
    }

    private function updateConfig(array $config):void
    {
        $config = json_encode($config);
        $this->dataBase->updateSourceReview($this->source_hash,['source_config'=>$config]);



    }
}