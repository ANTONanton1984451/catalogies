<?php


namespace parsing\platforms\topdealers;


use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;
use parsing\services\TaskQueueController;
use parsing\logger\LoggerManager;

/**
 * Class TopDealersModel
 * @package parsing\platforms\topdealers
 */
class TopDealersModel implements ModelInterface
{
    private const PLATFORM = 'topdealers';
    private const ERROR_MESSAGE = 'Cannot get information from link';
    /**
     * @var array
     * Информация которая остаётся неизменной для каждого отзыва:Платформа и ответ на отзыв
     */
    private $constInfo;
    /**
     * @var string Флаг из БЛ
     */
    private $handled;
    /**
     * @var string Флаг из БД
     */
    private $track;

    /**
     * @var DatabaseShell
     */
    private $dataBase;

    /**
     * @var TaskQueueController
     */
    private $taskQueueController;
    /**
     * @var array[] Оповещения.По дефолту стоит сообщение об ошибке
     */
    private $notifications = ['container'=>[
                                            'type'=>self::TYPE_ERROR,
                                            'content'=>self::ERROR_MESSAGE
                                        ]
                             ];

    public function __construct(DatabaseShell $db,TaskQueueController $controller)
    {
        $this->dataBase = $db;
        $this->taskQueueController = $controller;

        $this->constInfo['platform'] = self::PLATFORM;
        $this->constInfo['is_answered'] = false;

    }

    /**
     * @param $data
     * Метод в любом случае обновляет конфиги и метаданные.
     * Далее в зависимости от флага handled либо обновляет очередь,либо создаёт запись в очереди
     * и при наличии отзывов,кладёт их в БД
     */
    public function writeData($data)
    {
        $this->updateConfig($data['config']);
        $this->updateMetaInfo($data['meta_info']);

        if($this->isNewOrNonCompleted()){
            $this->writeNewData($data);
        }elseif ($this->isHandleOrNonUpdated()){
           $this->writeHandledData($data);
        }
    }

    /**
     * @param array $config
     * Установка конфигов и установка информации в оповещения
     */
    public function setConfig($config)
    {
        $this->constInfo['source_hash_key'] = $config['source_hash'];
        $this->handled = $config['handled'];
        $this->track = $config['track'];
        $this->setConstInfoNotifications($config);
    }

    /**
     * @return array
     */
    public function getNotifications(): array
    {
        return $this->notifications;
    }

    /**
     * @param array $config
     * Установка неизменной информации в оповещения
     */
    private function setConstInfoNotifications(array $config):void
    {
        $this->notifications['hash'] = $config['source_hash'];
        $this->notifications['track'] = $config['track'];
    }

    /**
     * @param array $data
     * Запись данных в БД при флаге NEW|NON_COMPLETED.
     * При наличии ошибки отлавивает её,проводит запись в лог и выставляет соответствующий флаг данному сурсу
     */
    private function writeNewData(array $data):void
    {

        $minimalDateReview = isset($data['reviews'])?
                             $data['reviews'][count($data['reviews'])-1]['date']:
                             0;
        try {
            if(!empty($data['reviews'])){
                $this->insertReviews($data['reviews']);
            }

            $this->insertTaskQueue(count($data['reviews']),
                                    $minimalDateReview,
                                    $this->constInfo['source_hash_key']);

            $this->dataBase->updateSourceReview($this->constInfo['source_hash_key'],
                                                ['handled'=>self::SOURCE_HANDLED]);

            $this->notifications['container']['content'] = $data['meta_info'];
            $this->notifications['container']['type'] = self::TYPE_METARECORD;
        }catch (\PDOException $e){
            $handled = $this->handled === self::SOURCE_NON_COMPLETED ?
                                          self::SOURCE_UNPROCESSABLE :
                                          self::SOURCE_NON_COMPLETED;

            LoggerManager::log(LoggerManager::ERROR,'Ошибка в NEW|TopDealersModel',['handled'=>$handled]);
            $this->dataBase->rollback($this->constInfo['source_hash_key'],$handled);
        }

    }

    /**
     * @param array $data
     * Запись данных в БД при флаге HANDLED|NON_UPDATED.
     * При наличии ошибки отлавивает её,проводит запись в лог и выставляет соответствующий флаг данному сурсу
     */
    private function writeHandledData(array $data):void
    {
        try {
            if(!empty($data['reviews'])){
                $this->setReviewNotifications($data['reviews']);
                $this->insertReviews($data['reviews']);
            }else{
                $this->notifications['container']['type'] = self::TYPE_EMPTY;
                $this->notifications['container']['content'] = [];
            }

            $this->taskQueueController->updateTaskQueue($this->constInfo['source_hash_key']);

        }catch (\PDOException $e){

            $handled = $this->handled === self::SOURCE_NON_UPDATED ?
                                          self::SOURCE_UNPROCESSABLE :
                                          self::SOURCE_NON_UPDATED;
            LoggerManager::log(LoggerManager::ERROR,'Ошибка в HANDLED|GoogleModel',['handled'=>$handled]);
            $this->dataBase->rollback($this->constInfo['source_hash_key'],$handled);

        }

    }

    /**
     * @param array $reviews
     * Агрегирует отзывы в поле notifications
     */
    private function setReviewNotifications(array $reviews):void
    {
        if($this->notifications['container']['type'] !== self::TYPE_ERROR){
            $this->notifications['container']['content'] = [];
        }
        $this->notifications['container']['type'] = self::TYPE_REVIEWS;
        switch ($this->track){
            case self::TRACK_ALL:
                $this->notifications['container']['content'] = array_merge($this->notifications['container']['content'],
                                                                           $reviews);
                break;
            case self::TRACK_NEGATIVE:
                $this->notifications['container']['content'] = array_merge($this->notifications['container']['content'],
                                                                           $this->catchNegative($reviews));
                break;
        }
    }

    /**
     * @param array $reviews
     * @return array
     * Собирает негативные отзывы
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
     * @param array $reviews
     */
    private function insertReviews(array $reviews):void
    {
        $this->dataBase->insertReviews($reviews,$this->constInfo);
    }

    /**
     * @param array $meta
     */
    private function updateMetaInfo(array $meta):void
    {
        $this->dataBase->updateSourceReview($this->constInfo['source_hash_key'],
                                            ['source_meta_info'=>json_encode($meta)]);
    }

    /**
     * @param int $countReviews
     * @param int $minimalDate
     * @param string $hash
     */
    private function insertTaskQueue(int $countReviews,int $minimalDate,string $hash):void {
            $this->taskQueueController->insertTaskQueue($hash, $countReviews,$minimalDate);
    }

    /**
     * @param array $config
     */
    private function updateConfig(array $config):void
    {
        $config = json_encode($config);
        $this->dataBase->updateSourceReview(
            $this->constInfo['source_hash_key'],
            ['source_config'=>$config]
        );
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