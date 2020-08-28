<?php

// todo: Прикрутить транзакции
// todo: Возвращать PDO_STATEMENT в

namespace parsing\platforms\zoon;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;

class ZoonModel implements ModelInterface {
    const HALF_YEAR_TIMESTAMP = 15552000;

    private $sourceConfig;
    private $sourceHash;
    private $handled;

    private $beforeHalfYearTimestamp;

    private $countReviews = 0;
    private $maxDate = 0;
    private $minDate = 0;

    private $constInfo = [
        'platform' => 'zoon',
        'rating' => 11,
        'tonal' => 'NEUTRAL',
    ];

    public function __construct() {
        $this->beforeHalfYearTimestamp = time() - self::HALF_YEAR_TIMESTAMP;
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

    public function writeData($records) {
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
        $this->minDate = $records[0]['date'];

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

        $sourceConfig = [
            'max_date' => $date,
            'old_hash' => $hash,
        ];

        (new DatabaseShell())->updateSourceReview($this->sourceHash, [
            'source_meta_info' => json_encode($sourceMeta),
            'source_config' => json_encode($sourceConfig),
            'handled' => "HANDLED",
        ]);
    }

    private function writeTaskQueue() {
        if ($this->handled === "NEW") {
            $reviewPerDay = $this->countReviews / ((time() - $this->minDate) / 86400);

            if ($reviewPerDay > 6) {
                $reviewPerDay = 6 * 4;
            } elseif ($reviewPerDay < 1) {
                $reviewPerDay = 1 * 4;
            } else {
                $reviewPerDay = round($reviewPerDay) * 4;
            }

            (new DatabaseShell())->insertTaskQueue([
                'source_hash_key' => $this->sourceHash,
                'last_parse_date' => time() / 3600,
                'review_per_day' => $reviewPerDay,
            ]);
        } else {
            (new DatabaseShell())->updateTaskQueue($this->sourceHash, [
                'last_parse_date' => time() / 3600
            ]);
        }
    }

}