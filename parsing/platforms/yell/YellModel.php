<?php

namespace parsing\platforms\yell;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;
use parsing\services\TaskQueueController;

class YellModel implements ModelInterface {

    private $sourceConfig;
    private $sourceMeta;
    private $sourceTrack;
    private $sourceHash;
    private $sourceStatus;

    private $beforeHalfYearTimestamp;

    private $countReviews = 0;
    private $maxDate = 0;
    private $minDate = 0;

    private $constInfo = [
        'platform' => 'yell',
    ];
    private $notify;

    public function __construct() {
        $this->beforeHalfYearTimestamp = time() - self::HALF_YEAR_TIMESTAMP;
    }

    public function setConfig($config) : void {
        
        $this->sourceStatus = $config['handled'];
        $this->sourceTrack = $config['track'];
        $this->sourceHash = $config['source_hash'];
        
        $this->constInfo['source_hash_key'] = $config['source_hash'];

        if ($this->sourceStatus === self::SOURCE_HANDLED) {
            $sourceConfig = json_decode($config['config'], true);
            $this->maxDate = $sourceConfig['max_date'];
        }
    }

    public function writeData($records) : void {
        if (is_object($records)) {
            $this->writeMetaRecord($records);
            $this->writeTaskQueue();
            $this->generateNotifications();

        } elseif (is_array($records)) {
            $this->writeReviews($records);
        }
    }

    private function writeReviews($records) {
        if ($this->sourceStatus === self::SOURCE_HANDLED && $this->maxDate !== 0) {
            $datePoint = $this->maxDate;
        } else {
            $datePoint = $this->beforeHalfYearTimestamp;
        }

        foreach ($records as $record) {
            if ($record['date'] > $datePoint) {
                $result[] = $record;
                $this->countReviews++;

                if ($record['date'] > $this->maxDate) {
                    $this->maxDate = $record['date'];
                }

                if ($record['date'] < $this->minDate) {
                    $this->minDate = $record['date'];
                }
            }
        }

        if (isset($result)) {
            (new DatabaseShell())->insertReviews($result, $this->constInfo);

            if ($this->sourceStatus === self::SOURCE_HANDLED) {
                $this->notify['type'] = self::TYPE_REVIEWS;
                $this->notify['container'] = $result;
            }
        }
    }

    private function writeMetaRecord($records) {
        $this->sourceMeta = [
            'count_reviews' => $records->count_reviews,
            'count_added_reviews' => $this->countReviews,
            'average_mark' => $records->average_mark,
        ];

        if ($this->maxDate != 0) {
            $date = $this->maxDate;
        } else {
            $date = $this->sourceConfig['max_date'];
        }

        if (isset($records->hash)) {
            $hash = $records->hash;
        } else {
            $hash = $this->sourceConfig['old_hash'];
        }

        $sourceConfig = [
            'max_date' => $date,
            'old_hash' => $hash,
        ];

        (new DatabaseShell())->updateSourceReview($this->sourceHash, [
            'source_meta_info' => json_encode($this->sourceMeta),
            'source_config' => json_encode($sourceConfig),
            'handled' => self::SOURCE_HANDLED,
        ]);
    }

    private function writeTaskQueue() {
        if ($this->sourceStatus === self::SOURCE_NEW) {
            (new TaskQueueController())->insertTaskQueue($this->sourceHash, $this->countReviews, $this->minDate);
        } else {
            (new TaskQueueController())->updateTaskQueue($this->sourceHash);
        }
    }

    private function generateNotifications() {
        $sourceConfig = [
            'hash' => $this->sourceHash,
            'track' => $this->sourceTrack,
        ];

        if ($this->sourceStatus === self::SOURCE_NEW) {
            $source_container = [
                'type' => self::TYPE_METARECORD,
                'container' => $this->sourceMeta,
            ];
            $this->notify = array_merge($source_container, $sourceConfig);

        } elseif ($this->sourceStatus === self::SOURCE_HANDLED && isset($this->notify['container'])) {
            $this->notify = array_merge($this->notify, $sourceConfig);
        }
    }

    public function getNotifications() : array {
        return $this->notify;
    }
}