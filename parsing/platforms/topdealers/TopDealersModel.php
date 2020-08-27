<?php


namespace parsing\platforms\topdealers;


use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;

class TopDealersModel implements ModelInterface
{
    private $constInfo;
    private $dataBase;

    public function __construct(DatabaseShell $db)
    {
        $this->dataBase = $db;
        $this->constInfo['platform'] = 'topdealers';
    }


    public function writeData($data)
    {
        $this->insertReviews($data['reviews']);
        $this->updateConfig($data['config']);
        $this->updateMetaInfo($data['meta_info']);
    }


    public function setConfig($config)
    {
        $this->constInfo['source_hash_key'] = $config['source_hash'];
    }

    private function insertReviews(array $reviews):void
    {
        $this->dataBase->insertReviews($reviews,$this->constInfo);
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