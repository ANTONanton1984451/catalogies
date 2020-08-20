<?php
// todo: Прописать логику хранения конфига в записи source_review

namespace parsing\platforms\yell;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;

class YellModel implements ModelInterface
{
    const HANDLED_TRUE = 'HANDLED';
    const HANDLED_FALSE = 'NEW';

    private $sourceInfo;
    private $constInfo;

    private $maxDate;

    public function setConfig($config) : void
    {
        $this->sourceInfo = $config;
        $this->constInfo = [
            'platform' => 'yell',
            'source_hash_key' => $this->sourceInfo['source_hash'],
        ];

        if (isset($config['source_config']['maxDate'])) {
            $this->maxDate = $config['source_config']['maxDate'];
        }
    }

    public function writeData($records) : void
    {
        if (isset($records['average_mark'])) {
            $this->updateSourceReview($records);
        } else {
            $this->writeReviews($records);
        }
    }

    private function writeReviews($records) : void
    {
        if ($this->sourceInfo['handled'] === self::HANDLED_FALSE) {
            $database->insertReviews($records, $this->constInfo);
        }

        if ($this->sourceInfo['handled'] === self::HANDLED_TRUE) {
            foreach ($records as $review) {
                if ($review['date'] > $this->maxDate) {
                    $result[] = $review;
                }
            }

            $database->insertReviews($result, $this->constInfo);
        }
    }

    private function updateSourceReview($records){}
}