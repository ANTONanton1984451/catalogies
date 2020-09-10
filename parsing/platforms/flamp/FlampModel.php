<?php
// todo: Проверка на успешность записи, если неудача, exception - rollback - logger

// todo: refactor writeMetaRecord
namespace parsing\platforms\flamp;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;
use parsing\services\TaskQueueController;

class FlampModel implements ModelInterface {
    private $maxDate = 0;
    private $minDate = PHP_INT_MAX;

    private $status;
    private $countReviews = 0;

    private $constInfo = [
        'platform' => 'flamp'
    ];

    private $sourceConfig;
    private $sourceHash;

    private $beforeHalfYearTimestamp;
    private $notifies;

    public function setConfig($config) {
        $this->beforeHalfYearTimestamp = time() - self::HALF_YEAR_TIMESTAMP;

        $this->status = $config['handled'];

        $this->sourceHash = $config['source_hash'];
        $this->constInfo['source_hash_key'] = $this->sourceHash;

        if ($this->status === self::SOURCE_HANDLED) {
            $this->sourceConfig = json_decode($config['config'], true);
            $this->maxDate = $this->sourceConfig['max_date'];
        }
    }

    public function writeData($records) {
        if (is_array($records)) {
            $this->writeReviews($records);
        }

        if (is_object($records) && $records->type === self::TYPE_METARECORD) {
                $this->writeMetaRecord($records);
                $this->writeTaskQueue();
        }
    }

    private function writeReviews($records) {
        if ($this->status === self::SOURCE_NEW) {
            $datePoint = $this->beforeHalfYearTimestamp;
        } elseif ($this->status === self::SOURCE_HANDLED) {
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
            (new DatabaseShell())->insertReviews($result, $this->constInfo);
        }
    }

    /** @param $records object */
    private function writeMetaRecord(object $records) {
        $sourceMeta = [
            'count_reviews' => $records->count_reviews,
            'count_added_reviews' => $this->countReviews,
            'average_mark' => $records->average_mark,
        ];

        if ($this->maxDate !== 0) {
            $date = $this->maxDate;
        } else {
            $date = $this->beforeHalfYearTimestamp;
        }

        if ($this->minDate !== PHP_INT_MAX) {
            $this->minDate = 0;
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

        var_dump($sourceConfig);


        (new DatabaseShell())->updateSourceReview($this->sourceHash, [
            'source_meta_info' => json_encode($sourceMeta),
            'source_config' => json_encode($sourceConfig),
            'handled' => "HANDLED",
        ]);
    }

    /** Обращается к стороннему сервису, которые формирует очередь последующей обработки этой ссылки */
    private function writeTaskQueue() {
        if ($this->status === self::SOURCE_NEW) {
            (new TaskQueueController())
                ->insertTaskQueue($this->sourceHash, $this->countReviews, $this->minDate);
        } else {
            (new TaskQueueController())->updateTaskQueue($this->sourceHash);
        }
    }

    public function getNotifications(): array{
        return $this->notifies;
    }
}