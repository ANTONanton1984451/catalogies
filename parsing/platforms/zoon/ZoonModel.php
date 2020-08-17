<?php

namespace parsing\platforms\zoon;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;

class ZoonModel implements ModelInterface
{
    private $constInfo;
    private $sourceInfo;
    private $maxDate;

    const HANDLED_TRUE  = 'HANDLED';
    const HANDLED_FALSE = 'NEW';

    public function __construct() {
        $this->constInfo['platform']            = 'zoon';
        $this->constInfo['rating']              = 11;
        $this->constInfo['tonal']               = 'NEUTRAL';
        $this->maxDate                          = 0;
    }

    public function setConfig($sourceInfo) {
        $this->sourceInfo = $sourceInfo;
        $this->constInfo['source_hash_key'] = $this->sourceInfo['source_hash'];
    }

    public function writeData($records) {
        if (is_array($records)) {
            $this->writeReviews($records);
        }

        if (is_object($records)) {
            $this->updateSourceReviewConfig($records);
        }
    }

    private function updateSourceReviewConfig($records) {
        $database = new DatabaseShell();

        if ($this->sourceInfo['handled'] === self::HANDLED_FALSE) {
            $database->updateSourceReview($this->sourceInfo['source_hash'], [
                'source_meta_info'  =>  json_encode($records),
                'source_config'     =>  getdate()[0],
                'handled'           =>  self::HANDLED_TRUE
                ]
            );
        } elseif ($this->sourceInfo['handled'] === self::HANDLED_TRUE) {
            $database->updateSourceReview($this->sourceInfo['source_hash'], [
                    'source_meta_info'  =>  json_encode($records),
                    'source_config'     =>  $this->maxDate,
                ]
            );
        }
    }

    private function writeReviews($records) {
        $database = new DatabaseShell();

        if ($this->sourceInfo['handled'] === self::HANDLED_FALSE) {
            $database->insertReviews($records, $this->constInfo);

        } elseif ($this->sourceInfo['handled'] === self::HANDLED_TRUE) {

            foreach ($records as $record) {
                if ($record['date'] > $this->sourceInfo['source_config']['date']) {
                    $result[] = $record;

                    if ($record['date'] > $max_date) {
                        $max_date = $record['date'];
                    }
                }
            }
            $database->insertReviews($result, $this->constInfo);
        }
    }
}