<?php


namespace parsing\platforms\google;


use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;
use parsing\logger\LoggerManager;
use parsing\services\TaskQueueController;

class GoogleModel implements ModelInterface
{

    private const PLATFORM = 'google';
    private const CONFIG_NOT_EMPTY = true;
    private const CONFIG_EMPTY = false;

    private $dataBase;
    private $handled;
    private $tempReviews;
    private $reviewCount = 0;
    private $source_hash;
    private $queueController;
    private $config_status;


    public function __construct(DatabaseShell $dbShell, TaskQueueController $queueController)
    {
        $this->dataBase = $dbShell;
        $this->queueController = $queueController;
    }

    /**
     * @param $data
     * Управляющий метод для вызова метода обработки данных определённого вида(NEW|HANDLED)
     */
    public function writeData($data)
    {
       if($this->handled === self::STATUS_NEW && $this->config_status === self::CONFIG_NOT_EMPTY){
           $this->writeNewData($data);
       }elseif($this->handled === self::STATUS_HANDLED && $this->config_status === self::CONFIG_NOT_EMPTY){
           $this->writeHandledData($data);
       }
        LoggerManager::log(LoggerManager::INFO,
                            'Insert data|GoogleModel',
                                    ['hash'=>$this->source_hash]);
    }


    public function setConfig($config)
    {   if($this->validateConfig($config)){
            $this->source_hash = $config['source_hash'];
            $this->handled = $config['handled'];
            $this->config_status = self::CONFIG_NOT_EMPTY;
        }else{
            LoggerManager::log(LoggerManager::ERROR,'Invalid config values|GoogleModel',
                                ['config'=>$config]);
        $this->config_status = self::CONFIG_EMPTY;
        }

    }

    private function validateConfig(array $config):bool
    {
        $isConfigValid = true;
        $handledNotExist = !in_array($config['handled'],$config);
        $hashNotExist = !in_array($config['source_hash'],$config);
        if($handledNotExist || $hashNotExist){
            $isConfigValid = false;
        }
        return $isConfigValid;
    }

    private function writeHandledData(array $data):void
    {

        if(!empty($data['reviews'])){
            $this->insertReviews($data['reviews']);
            $this->updateConfig($data['config']);
        }else{
            $columns = ['source_meta_info'=>json_encode($data['meta'])];
            $this->dataBase->updateSourceReview($this->source_hash,$columns);
            $this->queueController->updateTaskQueue($this->source_hash);
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
            $columns = [ 'source_meta_info' => json_encode($data['meta']),
                         'handled'=> self::STATUS_HANDLED];

            $this->dataBase->updateSourceReview($this->source_hash,$columns);
            $this->queueController->insertTaskQueue($this->reviewCount,
                                                    $this->tempReviews[count($this->tempReviews)-1]['date'],
                                                    $this->source_hash);
        }
    }

    private function insertReviews(array $reviews):void
    {
        $constInfo = ['source_hash_key'=>$this->source_hash,
                      'platform'=>self::PLATFORM];


        $this->dataBase->insertReviews($reviews,$constInfo);
    }

    private function updateConfig(array $config):void
    {
        $config = json_encode($config);
        $this->dataBase->updateSourceReview($this->source_hash,['source_config'=>$config]);



    }
}