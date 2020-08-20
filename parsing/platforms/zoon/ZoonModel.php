<?php

namespace parsing\platforms\zoon;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;

class ZoonModel implements ModelInterface
{
    private $constInfo;
    private $sourceInfo;
    private $maxDate;

    const HANDLED_TRUE = 'HANDLED';
    const HANDLED_FALSE = 'NEW';

    public function __construct()
    {
        $this->constInfo['platform'] = 'zoon';
        $this->constInfo['rating'] = 11;
        $this->constInfo['tonal'] = 'NEUTRAL';
        $this->maxDate = 0;
    }

    public function setConfig($sourceInfo) : void
    {
        $this->sourceInfo = $sourceInfo;
        $this->constInfo['source_hash_key'] = $this->sourceInfo['source_hash'];
    }

    public function writeData($records) : void
    {
        if (isset($records['count_reviews'])) {
            $this->updateSourceReviewConfig($records);
        } else {
            $this->writeReviews($records);
        }
    }

    private function updateSourceReviewConfig($records) : void
    {
        $database = new DatabaseShell();

        if ($this->sourceInfo['handled'] === self::HANDLED_FALSE) {
            $sourceMeta = [
                'count_reviews' => $records['count_reviews'],
                'average_mark' => $records['average_mark'],
            ];

            $sourceConfig = [
                'max_date' => getdate()[0],
                'old_hash' => $records['old_hash'],
            ];

            $database->updateSourceReview($this->sourceInfo['source_hash'], [
                'source_meta_info' => json_encode($sourceMeta),
                'source_config' => json_encode($sourceConfig),
                'handled' => self::HANDLED_TRUE
            ]);

        } elseif ($this->sourceInfo['handled'] === self::HANDLED_TRUE) {
            $sourceMeta = [
                'count_reviews' => $records['count_reviews'],
                'average_mark' => $records['average_mark'],
            ];

            $sourceConfig = [
                'max_date' => $this->maxDate,
                'old_hash' => $records['old_hash'],
            ];

            $database->updateSourceReview($this->sourceInfo['source_hash'], [
                'source_meta_info' => json_encode($sourceMeta),
                'source_config' => json_encode($sourceConfig)
            ]);
        }
    }

    private function writeReviews($records) : void
    {
        $database = new DatabaseShell();

        if ($this->sourceInfo['handled'] === self::HANDLED_FALSE) {
            $database->insertReviews($records, $this->constInfo);

        } elseif ($this->sourceInfo['handled'] === self::HANDLED_TRUE) {

            foreach ($records as $record) {
                if ($record['date'] > $this->sourceInfo['source_config']) {
                    $result[] = $record;

                    if ($record['date'] > $this->maxDate) {
                        $this->maxDate = $record['date'];
                    }
                }
            }
            $database->insertReviews($result, $this->constInfo);
        }
    }
}