<?php

namespace parsing\platforms\yell;

use parsing\factories\factory_interfaces\GetterInterface;
use parsing\logger\LoggerManager;
use parsing\DB\DatabaseShell;
use Unirest\Request;
use stdClass;
use phpQuery;

class YellGetter implements GetterInterface {

    const HOST = 'https://www.yell.ru/company/reviews/?';

    const EMPTY_RECORD_LENGTH = 64;
    const FIRST_PAGE = 1;

    private $activePage = self::FIRST_PAGE;

    private $isReadyQueue = false;
    private $queue = [];

    private $sourceStatus;
    private $metaRecord;
    private $oldHash;

    private $queryInfo = [
        'sort' => 'recent',
        'page' => self::FIRST_PAGE,
    ];

    public function setConfig($config) : void {

        if ($this->validateConfig($config) === true) {
            $this->sourceStatus = $config['handled'];
            $this->metaRecord = $this->generateMetaRecord($config['source'], $config['source_hash']);

            if ($this->sourceStatus === self::SOURCE_HANDLED) {
                $this->oldHash = json_decode($config['source_config'])->old_hash;
            }

        } else {
            $this->sourceStatus = self::SOURCE_UNPROCESSABLE;
            (new DatabaseShell())->updateSourceReview($config['source_hash'], ['handled' => 'UNPROCESSABLE']);

            $message = "Недостаточно данных для обработки ссылки";
            LoggerManager::log(LoggerManager::DEBUG, $message, [$config]);
        }
    }

    private function generateMetaRecord($source, $source_hash) : object {
        $sourcePage = Request::get($source)->body;

        $document = phpQuery::newDocument($sourcePage);

        $organizationId = $document->find('div.company')->attr('data-id');

        if ($organizationId == "") {
            $message = "Не удалось получить токен заведения";
            LoggerManager::log(LoggerManager::DEBUG, $message, [$source]);

            $this->sourceStatus = self::SOURCE_UNPROCESSABLE;
            (new DatabaseShell())->updateSourceReview($sourceHash, ['handled' => 'UNPROCESSABLE']);

        } else {
            $this->queryInfo['id'] = $organizationId;
        }


        $metaRecord = new stdClass();
        $metaRecord->type = self::TYPE_METARECORD;
        $metaRecord->average_mark = $document->find('div.company__rating span.rating__value')->text();
        $metaRecord->count_reviews = $document->find('span.rating__reviews span')->text();

        phpQuery::unloadDocuments();

        return $metaRecord;
    }

    public function getNextRecords() {
        switch ($this->sourceStatus) {
            case self::SOURCE_NEW:
                $records = $this->parseNewSource();
                break;

            case self::SOURCE_HANDLED:
                $records = $this->parseHandledSource();
                break;

            case self::SOURCE_UNPROCESSABLE:
                $records = $this->getEndCode();
                break;
        }

        return $records;
    }

    private function parseNewSource() {
        if ($this->isReadyQueue === false) {
            $firstReviews = $this->getReviews($this->activePage++);

            $this->saveFirstPage($firstReviews);
            $this->queue [] = $firstReviews;

            $this->queue [] = $this->getMetaRecord();
            $this->queue [] = $this->getEndCode();

            $this->isReadyQueue = true;
        }

        $records = array_shift($this->queue);

        if (is_object($records) && $records->type === self::TYPE_REVIEWS) {
            if ($this->isOverHalfYear($records->body) === false) {
                $reviews = $this->getReviews($this->activePage++);

                if ($this->isEmptyRecord($reviews) === false) {
                    array_push($this->queue, $this->getReviews($this->activePage++));
                }
            }
        }

        return $records;
    }

    private function parseHandledSource() {
        if ($this->isReadyQueue === false) {
            $records = $this->getReviews(self::FIRST_PAGE);

            if ($this->isEqualsHash($records) === false) {
                $this->queue [] = $records;
                $this->saveFirstPage($records['reviews']);
            }

            $this->queue [] = $this->getMetaRecord();
            $this->queue [] = $this->getEndCode();

            $this->isReadyQueue = true;
        }
        return array_pop($this->queue);
    }

    private function validateConfig (array $config) : bool {
        $response = Request::get($config['source']);

        $incorrectHttpCode = $response->code != 200;
        $handledNotExist = !array_key_exists("handled", $config);
        $trackNotExist = !array_key_exists("track", $config);

        if ($incorrectHttpCode || $handledNotExist || $trackNotExist) {
            return false;
        }

        return true;
    }

    private function isOverHalfYear($reviews) : bool {
        $pq = phpQuery::newDocument($reviews);
        $lastReviewDate = $pq->find('div.reviews__item:last span.reviews__item-added')->attr('content');
        phpQuery::unloadDocuments();

        $lastReviewDate = strtotime($lastReviewDate);
        $halfYearAgo = time() - self::HALF_YEAR_TIMESTAMP;

        return $lastReviewDate < $halfYearAgo;
    }

    private function getReviews($page) {
        $this->queryInfo['page'] = $page;
        $addQuery = http_build_query($this->queryInfo);

        $records = new stdClass();
        $records->body = Request::get(self::HOST . '&' . $addQuery)->body;
        $records->type = self::TYPE_REVIEWS;

        return $records;
    }

    private function getMetaRecord() : object {
        return $this->metaRecord;
    }

    private function getEndCode() : int {
        return self::END_CODE;
    }

    private function isEmptyRecord($records) {
        return strlen($records->body) === self::EMPTY_RECORD_LENGTH;
    }

    private function isEqualsHash(object $records) : bool {
        return $this->oldHash === md5($records->body);
    }

    private function saveFirstPage($records) : void {
        $this->metaRecord->hash = md5($records->body);
    }
}