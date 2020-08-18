<?php
// todo: Прописать логику хранения конфига в записи source_review

namespace parsing\platforms\yell;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;

class YellModel implements ModelInterface
{
    const HANDLED_TRUE  = 'HANDLED';
    const HANDLED_FALSE = 'NEW';

    private $sourceInfo;
    private $constInfo;

    private $maxDate;

    public function setConfig($config) {
        $this->sourceInfo = $config;
        $this->constInfo = [
            'platform'        =>  'yell',
            'source_hash_key' =>  $this->sourceInfo['source_hash'],
        ];

        if (isset($config['source_config']['maxDate'])) {
            $this->maxDate = $config['source_config']['maxDate'];
        }
    }

    public function writeData($reviews) {
        $database = new DatabaseShell();

        if ($this->sourceInfo['handled'] === self::HANDLED_FALSE) {
            $database->insertReviews($reviews, $this->constInfo);
        }

        if ($this->sourceInfo['handled'] === self::HANDLED_TRUE) {
            foreach ($reviews as $review) {
                if ($review['date'] > $this->maxDate) {
                    $result[] = $review;
                }
            }

            $database->insertReviews($result, $this->constInfo);
        }
    }
}