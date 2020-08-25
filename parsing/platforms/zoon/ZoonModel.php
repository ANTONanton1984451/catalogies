<?php
// todo: Рефактор модели

namespace parsing\platforms\zoon;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;
use parsing\ParserManager;

class ZoonModel implements ModelInterface
{
    private $constInfo;
    private $source;
    private $maxDate;
    private $sourceConfig;

    const HANDLED_TRUE = 'HANDLED';
    const HANDLED_FALSE = 'NEW';

    public function __construct()
    {
        $this->constInfo['platform'] = 'zoon';
        $this->constInfo['rating'] = 11;
        $this->constInfo['tonal'] = 'NEUTRAL';
        $this->maxDate = 0;
    }

    public function setConfig($source) : void
    {
        $this->source = $source;

        $this->sourceConfig = json_decode($this->source['source_config'], true);

        if (isset($this->sourceConfig['max_date'])) {
            $this->maxDate = $this->sourceConfig['max_date'];
        }

        $this->constInfo['source_hash_key'] = $this->source['source_hash'];
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

        if ($this->source['handled'] === self::HANDLED_FALSE) {
            $sourceMeta = [
                'count_reviews' => $records['count_reviews'],
                'average_mark' => $records['average_mark'],
            ];

            $sourceConfig = [
                'max_date' => getdate()[0],
                'old_hash' => $records['old_hash'],
            ];

            var_dump($sourceConfig);

            $database->updateSourceReview($this->source['source_hash'], [
                'source_meta_info' => json_encode($sourceMeta),
                'source_config' => json_encode($sourceConfig),
                'handled' => self::HANDLED_TRUE
            ]);

        } elseif ($this->source['handled'] === self::HANDLED_TRUE) {
            $sourceMeta = [
                'count_reviews' => $records['count_reviews'],
                'average_mark'  => $records['average_mark'],
            ];

            $sourceConfig['max_date'] = $this->maxDate;

            if (isset($records['old_hash'])) {
                $sourceConfig['old_hash'] = $records['old_hash'];
            } else {
                $sourceConfig['old_hash'] = $this->sourceConfig['old_hash'];
            }

            var_dump($sourceConfig);

            $database->updateSourceReview($this->source['source_hash'], [
                'source_meta_info' => json_encode($sourceMeta),
                'source_config' => json_encode($sourceConfig)
            ]);
        }
    }

    private function writeReviews($records) : void
    {
        $database = new DatabaseShell();

        if ($this->source['handled'] === self::HANDLED_FALSE) {
            $database->insertReviews($records, $this->constInfo);

        } elseif ($this->source['handled'] === self::HANDLED_TRUE) {
            $tempMaxDate = $this->maxDate;

            foreach ($records as $record) {
                if ($record['date'] > $this->maxDate) {
                    $result[] = $record;

                    if ($record['date'] > $tempMaxDate) {
                        $tempMaxDate = $record['date'];
                    }
                }
            }

            $this->maxDate = $tempMaxDate;

            if (isset($result)){
                $database->insertReviews($result, $this->constInfo);
            }
        }
    }
}