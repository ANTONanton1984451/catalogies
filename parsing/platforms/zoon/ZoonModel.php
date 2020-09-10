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
    private $sourceMeta;
    private $sourceTrack;
    private $sourceHash;
    private $sourceStatus;

    private $beforeHalfYearTimestamp;

    private $isFirstPage = true;
    private $countReviews = 0;
    private $maxDate = 0;
    private $minDate = 0;

    private $notify = [];

    private $constInfo = [
        'platform' => 'zoon',
        'rating' => -2,
        'tonal' => 'NONE',
    ];

    public function __construct() {
        $this->beforeHalfYearTimestamp = time() - self::HALF_YEAR_TIMESTAMP;
    }

    /**
     * Записывает в поля значения конфига для текущей ссылки

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

    /** Обрабатывает записи, в зависимости от их содержимого.

     * @param $records object|array
     */
    public function writeData($records) {
        if (is_object($records)) {
                $this->writeMetaRecord($records);
                $this->writeTaskQueue();
                $this->generateNotifications();

        } elseif (is_array($records)){
            $this->writeReviews($records);
        }
    }

    /** @param $records array */
    private function writeReviews(array $records) {
        if ($this->sourceStatus === self::SOURCE_HANDLED && $this->maxDate !== 0) {
            $datePoint = $this->maxDate;
        } else {
            $datePoint = $this->beforeHalfYearTimestamp;
        }

        if ($this->isFirstPage === true) {
            $this->minDate = $records[0]['date'];
            $this->isFirstPage = false;
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

    /** @param $records object */
    private function writeMetaRecord(object $records) {
        if (isset($records->hash)) {
            $hash = $records->hash;
        } else {
            $hash = $this->sourceConfig['old_hash'];
        }

        $sourceConfig = [
            'max_date' => $this->maxDate,
            'old_hash' => $hash,
        ];

        $this->sourceMeta = [
            'count_reviews' => $records->count_reviews,
            'count_added_reviews' => $this->countReviews,
            'average_mark' => $records->average_mark,
        ];

        (new DatabaseShell())->updateSourceReview($this->sourceHash, [
            'source_config' => json_encode($sourceConfig),
            'source_meta_info' => json_encode($this->sourceMeta),
            'handled' => self::SOURCE_HANDLED,
        ]);
    }

    /** Обращается к стороннему сервису, которые формирует очередь последующей обработки этой ссылки */
    private function writeTaskQueue() {
        if ($this->sourceStatus === self::SOURCE_NEW) {
            (new TaskQueueController())->insertTaskQueue($this->sourceHash, $this->countReviews, $this->minDate);
        } else {
            (new TaskQueueController())->updateTaskQueue($this->sourceHash);
        }
    }

    public function getNotifications() : array {
        return $this->notify;
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
}