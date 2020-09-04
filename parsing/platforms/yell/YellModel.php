<?php

namespace parsing\platforms\yell;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;
use parsing\services\TaskQueueController;

class YellModel implements ModelInterface
{

    private $sourceConfig;
    private $sourceHash;
    private $handled;

    private $beforeHalfYearTimestamp;
    private $maxDate = 0;
    private $minDate = 0;
    private $countReviews = 0;

    private $constInfo = [
        'platform' => 'yell',
    ];

    public function __construct() {
        $this->beforeHalfYearTimestamp = getdate()[0] - self::HALF_YEAR_TIMESTAMP;
    }

    public function setConfig($config) {
        $this->handled = $config['handled'];
        $this->sourceHash = $config['source_hash'];
        $this->constInfo['source_hash_key'] = $config['source_hash'];

        if ($this->handled === "HANDLED") {
            $sourceConfig = json_decode($config['config'], true);
            $this->maxDate = $sourceConfig['max_date'];
        }
    }

    public function writeData($records) : void
    {
        if (isset($records['average_mark'])) {
            $this->writeMetaRecord($records);
            $this->writeTaskQueue();
        } else {
            $this->writeReviews($records);
        }
    }

    private function writeReviews($records) {
        if ($this->handled === "NEW") {
            $datePoint = $this->beforeHalfYearTimestamp;
        } else {
            $datePoint = $this->maxDate;
        }

        $tempMaxDate = 0;

        foreach ($records as $record) {
            if ($record['date'] > $datePoint) {
                $result[] = $record;
                $this->countReviews++;

                if ($record['date'] > $tempMaxDate) {
                    $tempMaxDate = $record['date'];
                }

                if ($record['date'] < $this->minDate) {
                    $this->minDate = $record['date'];
                }
            }
        }

        if ($tempMaxDate > $this->maxDate && $tempMaxDate != 0) {
            $this->maxDate = $tempMaxDate;
        }

        if (isset($result)) {
            (new DatabaseShell())->insertReviews($result, $this->constInfo);
        }
    }

    private function writeMetaRecord($records) {
        $sourceMeta = [
            'count_reviews' => $records['count_reviews'],
            'average_mark' => $records['average_mark'],
        ];

        if ($this->maxDate != 0) {
            $date = $this->maxDate;
        } else {
            $date = $this->sourceConfig['max_date'];
        }

        if (isset($records['hash'])) {
            $hash = $records['hash'];
        } else {
            $hash = $this->sourceConfig['old_hash'];
        }

        if ($this->handled === "NEW") {
            $this->handled = "HANDLED";
        }

        $sourceConfig = [
            'max_date' => $date,
            'old_hash' => $hash,
        ];

        (new DatabaseShell())->updateSourceReview($this->sourceHash, [
            'source_meta_info' => json_encode($sourceMeta),
            'source_config' => json_encode($sourceConfig),
            'handled' => $this->handled,
        ]);
    }

    private function writeTaskQueue() {
        if ($this->handled === "NEW") {
            (new TaskQueueController())->insertTaskQueue($this->countReviews, $this->minDate, $this->sourceHash);
        } else {
            (new TaskQueueController())->updateTaskQueue($this->sourceHash);
        }
    }
}