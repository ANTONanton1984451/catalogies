<?php

// todo: Проверить, проводиться ли сортировка

namespace parsing\platforms\zoon;

use parsing\factories\factory_interfaces\GetterInterface;
use phpQuery;

class ZoonGetter implements GetterInterface {

    private $handled;
    private $sourceConfig;

    private $metaRecord;

    private $isEnd = false;
    private $isReviewsSended = false;

    private $activePage = 0;

    const EMPTY_PARAMETERS = '';
    const EMPTY_REVIEWS = '';

    const REVIEWS_LIMIT = 102;

    const QUERY_CONSTANT_PARAMETERS = 'https://zoon.ru/js.php?area=service&action=CommentList&owner[]=organization&' .
    'is_widget=1&strategy_options[with_photo]=0&allow_comment=0&allow_share=0&limit=' . self::REVIEWS_LIMIT;

    private $addQueryParameters = [
        'owner[]' => 'prof',
        'organization' => self::EMPTY_PARAMETERS,
        'skip' => self::EMPTY_PARAMETERS,
    ];

    public function setConfig($config) {
        $this->handled = $config['handled'];
        $this->metaRecord = $this->generateMetaRecord($config['source']);

        if ($this->handled === self::HANDLED_TRUE) {
            $this->sourceConfig = json_decode($config["config"], true);
        }
    }

    public function getNextRecords(){
        if ($this->handled === 'HANDLED') {
            $records = $this->parseHandledSource();
        } else {
            $records = $this->parseNewSource();
        }

        var_dump($this->isEnd);

        return $records;
    }

    private function parseHandledSource() {
        if ($this->isEnd == true) {
            $records = $this->getEndCode();
        } else {

            $records = $this->getReviews();
            if ($this->isEqualsHash(md5($records)) || $this->isReviewsSended == true) {
                $records = $this->getMetaRecord();
                $this->isEnd = true;
            } else {
                $records = json_decode($records);
                $this->isReviewsSended = true;
            }
        }

        return $records;
    }

    private function parseNewSource() {
        if ($this->isEnd == true) {
            $records = $this->getEndCode();
        } else {

            $records = $this->getReviews();
            if ($this->activePage == 1) {
                $this->metaRecord['hash'] = md5($records);
            }

            $records = json_decode($records);
            if ($records->list == self::EMPTY_REVIEWS) {
                $records = $this->getMetaRecord();
                $this->isEnd = true;
            }
        }

        return $records;
    }

    private function getReviews() {
        $this->addQueryParameters['skip'] = $this->activePage++ * self::REVIEWS_LIMIT;
        $addQuery = http_build_query($this->addQueryParameters);
        return file_get_contents(self::QUERY_CONSTANT_PARAMETERS . '&' . $addQuery);
    }

    private function getMetaRecord() {
        return $this->metaRecord;
    }

    private function getEndCode() {
        return self::END_CODE;
    }

    private function generateMetaRecord($source) {
        $file = file_get_contents($source);
        $document = phpQuery::newDocument($file);

        $this->addQueryParameters['organization'] = $document
            ->find('.comments-section')->attr('data-owner-id');

        $countReviews = $document->find('.fs-large.gray.js-toggle-content')->text();
        $metaRecord['count_reviews'] = explode(' ', trim($countReviews))[0];
        $metaRecord['average_mark'] = $document->find('span.rating-value')->text();

        phpQuery::unloadDocuments();

        return $metaRecord;
    }

    private function isEqualsHash($hash) {
        return $hash === $this->sourceConfig['old_hash'];
    }
}