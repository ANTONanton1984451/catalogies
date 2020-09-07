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

    private $constInfo;

    private $handled;
    private $track;

    private $dataBase;
    private $taskQueueController;

    private $notifications;

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

        if(!empty($data['review'])){
            $this->insertReviews($data['reviews']);
            $this->setNotifications($data['reviews']);
        }

        if($this->handled === self::SOURCE_NEW){

            $minimalDateReview = isset($data['reviews'])
                                 ? $data['reviews'][count($data['reviews'])-1]['date'] : 0;

            $this->insertTaskQueue(count($data['reviews']),
                                   $minimalDateReview,
                                   $this->constInfo['source_hash_key']);

            $this->dataBase->updateSourceReview($this->constInfo['source_hash_key'],['handled'=>'HANDLED']);

        }elseif ($this->handled === self::SOURCE_HANDLED){
            $this->taskQueueController->updateTaskQueue($this->constInfo['source_hash_key']);
        }
    }

    /**
     * @param $config
     */
    public function setConfig($config)
    {
        $this->constInfo['source_hash_key'] = $config['source_hash'];
        $this->handled = $config['handled'];

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
     * @return array
     */
    public function getNotifications(): array
    {
        return $this->notifications;
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
     * @param string $meta
     *
     */
    private function updateMetaInfo(string $meta):void
    {
        $this->dataBase->updateSourceReview(
            $this->constInfo['source_hash_key'],
            ['source_meta_info'=>$meta]
        );
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