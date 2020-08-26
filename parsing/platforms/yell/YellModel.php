<?php
// todo: Прописать логику хранения конфига в записи source_review

namespace parsing\platforms\yell;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\ModelInterface;

class YellModel implements ModelInterface
{
    const HANDLED_TRUE = 'HANDLED';
    const HANDLED_FALSE = 'NEW';

    private $sourceInfo;
    private $constInfo;

    private $maxDate;
    private $oldHash;

    public function setConfig($config) : void
    {
        $this->sourceInfo = $config;
        $this->constInfo = [
            'platform' => 'yell',
            'source_hash_key' => $this->sourceInfo['source_hash'],
        ];

        if (isset($config['source_config'])) {
            $this->maxDate = json_decode($config['source_config'])['max_date'];
            $this->oldHash = json_decode($config['source_config'])['old_hash'];
        }
    }

    public function writeData($records) : void
    {
        if (isset($records['average_mark'])) {
            $this->updateSourceReview($records);
        } else {
            $this->writeReviews($records);
        }
    }

    private function writeReviews($records) : void
    {
        $database = new DatabaseShell();

        if ($this->sourceInfo['handled'] === self::HANDLED_FALSE) {
            // todo: Проверка на полгода
            $database->insertReviews($records, $this->constInfo);
        }

        if ($this->sourceInfo['handled'] === self::HANDLED_TRUE) {
            $tempMaxDate = $this->maxDate;

            foreach ($records as $review) {
                if ($review['date'] > $this->maxDate) {
                    $result[] = $review;

                    if ($review['date'] > $tempMaxDate) {
                        $tempMaxDate = $review['date'];
                    }
                }
            }

            $this->maxDate = $tempMaxDate;

            $database->insertReviews($result, $this->constInfo);
        }
    }

    private function updateSourceReview($records) {
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
                'average_mark'  => $records['average_mark'],
            ];

            $sourceConfig['max_date'] = $this->maxDate;

            if (isset($records['old_hash'])) {
                $sourceConfig['old_hash'] = $records['old_hash'];
            } else {
                $sourceConfig['old_hash'] = $this->oldHash;
            }

            $database->updateSourceReview($this->sourceInfo['source_hash'], [
                'source_meta_info' => json_encode($sourceMeta),
                'source_config' => json_encode($sourceConfig)
            ]);
        }
    }
}