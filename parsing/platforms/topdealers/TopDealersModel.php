<?php


namespace parsing\platforms\topdealers;


use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;
use parsing\services\TaskQueueController;

/**
 * Class TopDealersModel
 * @package parsing\platforms\topdealers
 */
class TopDealersModel implements ModelInterface
{
    private const PLATFORM = 'topdealers';
    private const ERROR_MESSAGE = 'Cannot get information from link';

    private $constInfo;

    private $handled;
    private $track;

    private $dataBase;
    private $taskQueueController;
    //todo:Нужно продумать момент,когда ссылка ломанная
    private $notifications = ['type'=>self::TYPE_ERROR,
                              'container'=>self::ERROR_MESSAGE];

    public function __construct(DatabaseShell $db,TaskQueueController $controller)
    {
        $this->dataBase = $db;
        $this->taskQueueController = $controller;

        $this->constInfo['platform'] = self::PLATFORM;
        $this->constInfo['is_answered'] = false;

    }

    /**
     * @param $data
     * Метод в любом случае обновляет конфиги и метаданные,при наличии отзывов,кладёт их в БД
     * Далее в зависимости от флага handled либо обновляет очередь,либо создаёт запись в очереди
     */
    public function writeData($data)
    {

        $this->updateConfig($data['config']);
        $this->updateMetaInfo($data['meta_info']);
        if(!empty($data['reviews'])){
            $this->insertReviews($data['reviews']);
        }

        if($this->handled === self::SOURCE_NEW){
            $this->writeNewData($data);
        }elseif ($this->handled === self::SOURCE_HANDLED){
           $this->writeHandledData($data);
        }
    }

    /**
     * @param $config
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
     */
    private function setConstInfoNotifications(array $config):void
    {
        $this->notifications['hash'] = $config['source_hash'];
        $this->notifications['track'] = $config['track'];
    }

    /**
     * @param array $data
     */
    private function writeNewData(array $data):void
    {
        $this->notifications['container'] = $data['meta_info'];
        $this->notifications['type'] = self::TYPE_METARECORD;

        $minimalDateReview = isset($data['reviews'])
                             ?$data['reviews'][count($data['reviews'])-1]['date']
                             : 0;

        $this->insertTaskQueue(count($data['reviews']),
            $minimalDateReview,
            $this->constInfo['source_hash_key']);

        $this->dataBase->updateSourceReview($this->constInfo['source_hash_key'],['handled'=>self::SOURCE_HANDLED]);
    }

    /**
     * @param array $data
     */
    private function writeHandledData(array $data):void
    {
        if(!empty($data['reviews'])){
            $this->setReviewNotifications($data['reviews']);
        }
        if($this->notifications['type'] === self::TYPE_ERROR){
            $this->notifications['container'] = [];
        }
        $this->taskQueueController->updateTaskQueue($this->constInfo['source_hash_key']);
    }

    /**
     * @param array $reviews
     * собирает отзывы для уведомления в отдельный массив
     */
    private function setReviewNotifications(array $reviews):void
    {
        $this->notifications['type'] = self::TYPE_REVIEWS;
        $this->notifications['container'] = [];
        switch ($this->track){
            case self::TRACK_ALL:
                $this->notifications['container'] = array_merge($this->notifications['container'],$reviews);
                break;
            case self::TRACK_NEGATIVE:
                $this->notifications['container'] = array_merge($this->notifications['container'],$this->catchNegative($reviews));
                break;
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
    private function insertTaskQueue(int $countReviews,int $minimalDate,string $hash):void
    {
        $this->taskQueueController->insertTaskQueue($countReviews,$minimalDate,$hash);
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
}