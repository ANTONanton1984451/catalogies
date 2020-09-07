<?php

// todo: Прикрутить транзакции
// todo: Проводить логирование результата работы
// todo: Проверка на успешность записи, если неудача, exception - rollback - logger

namespace parsing\platforms\zoon;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;
use parsing\services\TaskQueueController;

class ZoonModel implements ModelInterface {

    private $sourceConfig;
    private $sourceHash;
    private $sourceStatus;
    private $sourceTrack;

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

    /**
     * Записывает в поля значения конфига для текущей ссылки
     *
     * @param $config
     */
    public function setConfig($config) {
        $this->sourceStatus = $config['handled'];
        $this->sourceHash = $config['source_hash'];
        $this->sourceTrack = $config['track'];
        $this->constInfo['source_hash_key'] = $config['source_hash'];
        if ($this->sourceStatus === self::SOURCE_HANDLED) {
            $sourceConfig = json_decode($config['config'], true);
            $this->maxDate = $sourceConfig['max_date'];
        }
    }

    /**
     * Обрабатывает записи, в зависимости от их содержимого.
     *
     * @param $records object|array
     */
    public function writeData($records) {
        if (is_object($records)) {
            if ($records->type === self::TYPE_METARECORD){
                $this->writeMetaRecord($records);
                $this->writeTaskQueue();
            }

        } elseif (is_array($records)){
            $this->writeReviews($records);
        }

    }

    /** @param $records array */
    private function writeReviews(array $records) {

        if ($this->sourceStatus === self::SOURCE_NEW) {
            $datePoint = $this->beforeHalfYearTimestamp;
        } elseif ($this->sourceStatus === self::SOURCE_HANDLED) {
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

    /** @param $records object */
    private function writeMetaRecord(object $records) {
        $sourceMeta = [
            'count_reviews' => $records->count_reviews,
            'average_mark' => $records->average_mark,
        ];

        if ($this->maxDate != 0) {
            $date = $this->maxDate;
        } else {
            $date = $this->sourceConfig['max_date'];
        }

        if (isset($records->old_hash)) {
            $hash = $records->old_hash;
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

    /** Обращается к стороннему сервису, которые формирует очередь последующей обработки этой ссылки */
    private function writeTaskQueue() {
        if ($this->sourceStatus === "NEW") {
            (new TaskQueueController())->insertTaskQueue($this->countReviews, $this->minDate, $this->sourceHash);
        } else {
            (new TaskQueueController())->updateTaskQueue($this->sourceHash);
        }
    }

}