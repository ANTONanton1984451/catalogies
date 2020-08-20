<?php
// todo: Сделать сравнение данных при обычном парсинге через повторение js скрипта, и при помощи браузера
// todo: Изучить тему, связанную с использованием прокси, и избеганием бана от площадок.

// todo: Проверка на правильный ответ от сервера

namespace parsing\platforms\zoon;

use parsing\factories\factory_interfaces\GetterInterface;
use phpQuery;

class ZoonGetter implements GetterInterface
{
    const REVIEWS_LIMIT = 100;

    const STATUS_REVIEWS = 0;
    const STATUS_SOURCE_REVIEW = 1;
    const STATUS_END = 2;

    const EMPTY_RECORD = '';

    const HANDLED_TRUE = 'HANDLED';

    const HOST = 'https://zoon.ru/js.php?';
    const PREFIX_SKIP = 'skip';

    const QUERY_CONST_PARAMS = [
        'area' => 'service',
        'action' => 'CommentList',
        'owner[]' => 'organization',
        'is_widget' => 1,
        'strategy_options[with_photo]' => 0,
        'allow_comment' => 0,
        'allow_share' => 0,
        'limit' => self::REVIEWS_LIMIT,
    ];

    private $status;

    private $source;
    private $handled;               // Флаг, который обозначает, обрабатывалась ли ссылка ранее
    private $oldHash;               // Хэш первой страницы отзывов, полученный в предыдущие итерации этой ссылки

    private $addQueryParams = [];
    private $activeListReviews;



    public function __construct()
    {
        $this->addQueryParams['owner[]'] = 'prof';      // Строка нужна для корректного формирования url запроса
        $this->activeListReviews = 0;
        $this->status = self::STATUS_REVIEWS;
    }

    public function setConfig($config): void
    {
        $this->handled = $config['handled'];
        $this->source = $config['source'];

        if ($this->handled === self::HANDLED_TRUE) {
            $this->oldHash = json_decode($config['source_config'], true)['old_hash'];
        }

        $this->getOrganizationId();
    }

    private function getOrganizationId() : void
    {
        $file = file_get_contents($this->source);
        $document = phpQuery::newDocument($file);
        $this->addQueryParams['organization'] = $document->find('.comments-section')->attr('data-owner-id');
        phpQuery::unloadDocuments();
    }



    public function getNextRecords()
    {
        switch ($this->status) {
            case self::STATUS_REVIEWS:
                $records = $this->getReviews();
                $records = $this->validateReviews($records);    // Здесь может генерироваться Meta информация
                break;

            case self::STATUS_SOURCE_REVIEW:
                $records = $this->getMetaInfo();
                break;

            case self::STATUS_END;
                $records = $this->getEndCode();
                break;
        }
        return $records;
    }

    private function getReviews() : string
    {
        $this->addQueryParams[self::PREFIX_SKIP] = $this->activeListReviews++ * self::REVIEWS_LIMIT;
        return file_get_contents(
            self::HOST .
            http_build_query(self::QUERY_CONST_PARAMS) .
            '&' .
            http_build_query($this->addQueryParams)
        );
    }

    private function getMetaInfo() : array
    {
        $file = file_get_contents($this->source);
        $document = phpQuery::newDocument($file);

        $countReviews = $document->find('.fs-large.gray.js-toggle-content')->text();
        $records['count_reviews'] = explode(' ', trim($countReviews))[0];
        $records['average_mark'] = $document->find('span.rating-value')->text();

        $this->addQueryParams[self::PREFIX_SKIP] = 0;
        $firstReviews = file_get_contents
        (self::HOST
            . http_build_query(self::QUERY_CONST_PARAMS)
            . '&' . http_build_query($this->addQueryParams)
        );

        $records['old_hash'] = md5($firstReviews);

        phpQuery::unloadDocuments();

        $this->status = self::STATUS_END;

        return $records;
    }

    private function getEndCode() : int
    {
        return self::END_CODE;
    }

    private function validateReviews($records)
    {
        if (md5($records) == $this->oldHash) {
            $records = $this->getMetaInfo();
            return $records;
        }

        $records = json_decode($records);
        if ($records->remain == 0 || $this->handled === self::HANDLED_TRUE) {
            $this->status = self::STATUS_SOURCE_REVIEW;
        }

        if ($records->list == self::EMPTY_RECORD) {
            $records = $this->getMetaInfo();
        }

        return $records;
    }
}