<?php

namespace parsing\platforms\zoon;

use parsing\factories\factory_interfaces\GetterInterface;
use phpQuery;

class ZoonGetter implements GetterInterface
{
    private $status;
    private $handled;

    private $addQueryParameters;
    private $activeListReviews;

    private $metaRecord;
    private $oldHash;

    const HOST = 'https://zoon.ru/js.php?';

    const QUERY_CONSTANT_PARAMETERS = [
        'area' => 'service',
        'action' => 'CommentList',
        'owner[]' => 'organization',
        'is_widget' => 1,
        'strategy_options[with_photo]' => 0,
        'allow_comment' => 0,
        'allow_share' => 0,
        'limit' => self::REVIEWS_LIMIT,
    ];

    const REVIEWS_LIMIT = 100;

    const SKIP = 'skip';

    const STATUS_REVIEWS = 0;
    const STATUS_META_RECORD = 1;
    const STATUS_END = 2;



    public function __construct(){
        $this->addQueryParameters['owner[]'] = 'prof';
        $this->activeListReviews = 0;
        $this->status = self::STATUS_REVIEWS;
    }

    public function setConfig($config) {
        $this->setMetaRecord($config['source']);

        $this->handled = $config['handled'];

        if ($this->handled === self::HANDLED_TRUE) {
            $this->oldHash = json_decode($config['source_config'], true)['old_hash'];
        }
    }

    private function setMetaRecord($source) {
        $file = file_get_contents($source);
        $document = phpQuery::newDocument($file);

        $this->addQueryParameters['organization'] = $document
            ->find('.comments-section')->attr('data-owner-id');

        $countReviews = $document->find('.fs-large.gray.js-toggle-content')->text();
        $this->metaRecord['count_reviews'] = explode(' ', trim($countReviews))[0];
        $this->metaRecord['average_mark'] = $document->find('span.rating-value')->text();

        phpQuery::unloadDocuments();
    }

    public function getNextRecords(){
        switch ($this->status) {
            case self::STATUS_REVIEWS:
                $records = $this->getReviews();
                $records = $this->validateReviews($records);
                break;

            case self::STATUS_META_RECORD:
                $records = $this->getMetaRecord();
                break;

            case self::STATUS_END:
                $records = $this->getEndCode();
                break;

            default:
                throw new Exception("Unknown Status");
        }

    if ($this->status != self::STATUS_REVIEWS) {
        if (isset($records['average_mark'])) {
            var_dump($records);
        }
    }

    return $records;
}

    private function getReviews(){
        $this->addQueryParameters[self::SKIP] = $this->activeListReviews++ * self::REVIEWS_LIMIT;

        $query = self::HOST . http_build_query(self::QUERY_CONSTANT_PARAMETERS) .
            '&' . http_build_query($this->addQueryParameters);

        return file_get_contents($query);
    }

    private function getMetaRecord(){
        return $this->metaRecord;
    }

    private function getEndCode(){
        return self::END_CODE;
    }

    private function validateReviews($records)
    {
        if ($this->handled === self::HANDLED_TRUE) {
            if ($this->oldHash == md5($records)) {
                $this->status = self::STATUS_END;
                $records = $this->getMetaRecord();
                return $records;
            }
        } elseif ($this->activeListReviews == 1) {
            $this->metaRecord['old_hash'] = md5($records);
        }

        $records = json_decode($records);

        if ($records->remain == 0 && $records->list != '') {
           $this->status = self::STATUS_META_RECORD;
        }

        if ($records->list == '') {
            $this->status = self::STATUS_END;
            $records = $this->getMetaRecord();
        }

        return $records;
    }
}