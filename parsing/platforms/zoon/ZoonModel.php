<?php

// todo: Прикрутить транзакции
// todo: Проводить логирование результата работы логгера

namespace parsing\platforms\zoon;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;
use parsing\services\TaskQueueController;

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

    /**
     * Записывает в поля значения конфига для текущей ссылки
     *
     * @param $config
     */
    public function setConfig($config) {
        // todo: В случае, если в конфиге недостаточно информации для записи - exception, logger

        $this->handled = $config['handled'];
        $this->sourceHash = $config['source_hash'];
        $this->constInfo['source_hash_key'] = $config['source_hash'];

        if ($this->handled === "HANDLED") {
            $sourceConfig = json_decode($config['config'], true);
            $this->maxDate = $sourceConfig['max_date'];
        }
    }

    /**
     * Обрабатывает записи, в зависимости от их содержимого.
     *
     * @param $records
     */
    public function writeData($records) {
        if (isset($records['average_mark'])) {
            $this->writeMetaRecord($records);
            $this->writeTaskQueue();
        } else {
            $this->writeReviews($records);
        }
    }

    /**
     * @param $records array
     */
    private function writeReviews(array $records) {
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
            // todo: Проверка на успешность записи, если неудача, exception - rollback - logger
            (new DatabaseShell())->insertReviews($result, $this->constInfo);
        }
    }

    /**
     * @param $records array
     */
    private function writeMetaRecord(array $records) {
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

        // todo: Проверка на наличие всех необходимы записей, иначе exception - logger

        // todo: Проверка успешности записи, иначе exception - rollback - logger
        (new DatabaseShell())->updateSourceReview($this->sourceHash, [
            'source_meta_info' => json_encode($sourceMeta),
            'source_config' => json_encode($sourceConfig),
            'handled' => "HANDLED",
        ]);
    }

    /**
     * Обращается к стороннему сервису, которые формирует очередь последующей обработки этой ссылки
     */
    private function writeTaskQueue() {
        // todo: Проверка успешности записи, иначе exception - rollback - logger
        if ($this->handled === "NEW") {
            (new TaskQueueController())->insertTaskQueue($this->countReviews, $this->minDate, $this->sourceHash);
        } else {
            (new TaskQueueController())->updateTaskQueue($this->sourceHash);
        }
    }

}