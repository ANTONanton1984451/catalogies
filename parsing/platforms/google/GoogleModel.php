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
    private $queueController;

    private $handled;
    private $track;

    private $notifications = [];
    private $tempReviews;
    private $reviewCount = 0;

    private $source_hash;
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
       if($this->handled === self::SOURCE_NEW && $this->config_status === self::CONFIG_NOT_EMPTY){
           $this->writeNewData($data);
       }elseif($this->handled === self::SOURCE_HANDLED && $this->config_status === self::CONFIG_NOT_EMPTY){
           $this->writeHandledData($data);
       }
        LoggerManager::log(LoggerManager::INFO,
                            'Insert data|GoogleModel',
                                    ['hash'=>$this->source_hash]);
    }

    /**
     * @param $config
     * Метод вылидирует конфиги и если они не валидны,то фиксирует этот момент в логе и завершает работу модели
     */
    public function setConfig($config)
    {   if($this->validateConfig($config)){
            $this->source_hash = $config['source_hash'];
            $this->handled = $config['handled'];
            $this->track = $config['track'];
            $this->config_status = self::CONFIG_NOT_EMPTY;
        }else{
            LoggerManager::log(LoggerManager::ERROR,'Invalid config values|GoogleModel',
                                ['config'=>$config]);
        $this->config_status = self::CONFIG_EMPTY;
        }

    }

    /**
     * @return array
     */
    public function getNotifications():array
    {
        return $this->notifications;
    }

    /**
     * @param array $config
     * @return bool
     * Метод валидирует конфиги(на данный момент проверяет их на иссет) и возвращает ответ либо об
     * успешной либо о неуспешной валидации
     */
    private function validateConfig(array $config):bool
    {
        $isConfigValid = true;
        $handledNotExist = !in_array($config['handled'],$config);
        $hashNotExist = !in_array($config['source_hash'],$config);
        $trackNotExist = !in_array($config['track'],$config);
        if($handledNotExist || $hashNotExist || $trackNotExist){
            $isConfigValid = false;
        }
        return $isConfigValid;
    }

    /**
     * @param array $data
     * Метод записывает данные сурсы после первичной обработки
     * В случае когда нет отзывовов,то он считает,что пора обновлять мета информацию
     */
    private function writeHandledData(array $data):void
    {

        if(!empty($data['reviews'])){
            $this->insertReviews($data['reviews']);
            $this->setNotifications($data['reviews']);
            $this->updateConfig($data['config']);
        }else{
            $columns = ['source_meta_info'=>json_encode($data['meta'])];
            $this->dataBase->updateSourceReview($this->source_hash,$columns);
            $this->queueController->updateTaskQueue($this->source_hash);
        }
    }

    /**
     * @param array $data
     * Метод записывает данные сурсы после первичной обработки
     * В случае когда нет отзывовов,то он считает,что пора записывать мета информацию
     */
    private function writeNewData(array $data):void
    {
        if(!empty($data['reviews'])){
            $this->tempReviews = $data['reviews'];//????
            $this->reviewCount += count($data['reviews']);
            $this->insertReviews($data['reviews']);
            $this->updateConfig($data['config']);

        }else{
            $columns = [ 'source_meta_info' => json_encode($data['meta']),
                         'handled'=> self::SOURCE_HANDLED];
            $this->notifications = $data['meta'];
            $this->dataBase->updateSourceReview($this->source_hash,$columns);
            $this->queueController->insertTaskQueue($this->source_hash,
                                                    $this->reviewCount,
                                                    $this->tempReviews[count($this->tempReviews)-1]['date']);
        }
    }

    /**
     * @param array $reviews
     */
    private function insertReviews(array $reviews):void
    {
        $constInfo = ['source_hash_key'=>$this->source_hash,
                      'platform'=>self::PLATFORM];
        $this->dataBase->insertReviews($reviews,$constInfo);
    }

    /**
     * @param array $reviews
     * собирает отзывы для уведомления в отдельный массив
     */
    private function setNotifications(array $reviews):void
    {
        switch ($this->track){
            case self::TRACK_ALL:
                $this->notifications = array_merge($this->notifications,$reviews);
                break;
            case self::TRACK_NEGATIVE:
                $this->notifications = array_merge($this->notifications,$this->catchNegative($reviews));
                break;
            default:
                $this->notifications = [];
        }
    }

    /**
     * @param array $reviews
     * @return array
     */
    private function catchNegative(array $reviews):array
    {
        $negativeReviews = [];
        foreach ($reviews as $review){
            if($review['tonal'] === self::TONAL_NEGATIVE){
                $negativeReviews[] = $review;
            }
        }
        return $negativeReviews;
    }

    /**
     * @param array $config
     */
    private function updateConfig(array $config):void
    {
        $config = json_encode($config);
        $this->dataBase->updateSourceReview($this->source_hash,['source_config'=>$config]);
    }

}