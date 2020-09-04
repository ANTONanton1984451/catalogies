<?php

namespace parsing\platforms\flamp;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;
use parsing\services\TaskQueueController;

class FlampModel implements ModelInterface
{
    const HALF_YEAR_TIMESTAMP = 15552000;

    private $maxDate = PHP_INT_MIN;
    private $minDate = PHP_INT_MAX;

    private $status;
    private $countReviews = 0;

    private $constInfo = ['platform' => 'flamp'];
    private $sourceConfig;

    private $beforeHalfYearTimestamp;

    const TYPE_REVIEWS = 'reviews';
    const TYPE_METARECORD = 'meta';


    public function setConfig($config) {
        $this->beforeHalfYearTimestamp = time() - self::HALF_YEAR_TIMESTAMP;

        $this->status = $config['handled'];
        $this->constInfo['source_hash_key'] = $config['source_hash'];

        if ($this->status === self::STATUS_HANDLED) {
            $this->sourceConfig = json_decode($config['config'], true);
            $this->maxDate = $sourceConfig['max_date'];
        }
    }

    public function writeData($records) {
        if ($records->type === self::TYPE_METARECORD) {
            $this->writeMetaRecord($records);
            $this->writeTaskQueue();

        } elseif ($records->type === self::TYPE_REVIEWS) {
            $this->writeReviews($records);
        }
    }

    private function writeReviews($records) {
        if ($this->status === self::STATUS_NEW) {
            $datePoint = $this->beforeHalfYearTimestamp;
        } elseif ($this->status === self::STATUS_HANDLED) {
            $datePoint = $this->maxDate;
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
            // todo: Проверка на успешность записи, если неудача, exception - rollback - logger
            (new DatabaseShell())->insertReviews($result, $this->constInfo);
        }
    }

    /** @param $records object */
    private function writeMetaRecord(object $records)
    {
        $sourceMeta = [
            'count_reviews' => $records->count_reviews,
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

        (new DatabaseShell())->updateSourceReview($this->sourceConfig['source_hash'], [
            'source_meta_info' => json_encode($sourceMeta),
            'source_config' => json_encode($sourceConfig),
            'handled' => "HANDLED",
        ]);
    }

    /** Обращается к стороннему сервису, которые формирует очередь последующей обработки этой ссылки */
    private function writeTaskQueue() {
        // todo: Проверка успешности записи, иначе exception - rollback - logger
        if ($this->status === "NEW") {
            (new TaskQueueController())
                ->insertTaskQueue($this->countReviews, $this->minDate, $this->sourceConfig['source_hash']);
        } else {
            (new TaskQueueController())->updateTaskQueue($this->sourceConfig['source_hash']);
        }
    }
}