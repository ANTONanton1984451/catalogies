<?php

// todo: Рефактор getReviews(),
// todo: Добавление типа записей и проверки на них

namespace parsing\platforms\yell;

use parsing\factories\factory_interfaces\GetterInterface;
use parsing\DB\DatabaseShell;
use Unirest\Request;
use phpQuery;
use stdClass;

class YellGetter implements GetterInterface {

    const HOST = 'https://www.yell.ru/company/reviews/?';

    const EMPTY_RECORD_LENGTH = 64;
    const FIRST_PAGE = 1;

    private $isEnd = false;
    private $activePage = 1;

    private $isReadyQueue = false;
    private $queue = [];

    private $sourceStatus;
    private $oldHash;

    private $queryInfo;
    private $metaRecord;

    public function __construct() {
        $this->queryInfo = [
            'sort' => 'recent',
            'page' => $this->activePage,
        ];
    }

    public function setConfig($config) : void {
        if ($this->validateConfig($config) === true) {
            $this->sourceStatus = $config['handled'];
            $this->metaRecord = $this->generateMetaRecord($config['source']);

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

    private function generateMetaRecord($source) : object {
        $sourcePage = Request::get($source)->body;

        $document = phpQuery::newDocument($sourcePage);

        $this->queryInfo['id'] = $document->find('div.company')->attr('data-id');

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
        if ($this->isEnd != true) {
            $records = $this->getReviews($this->activePage);

            if ($this->activePage === self::FIRST_PAGE) {
                $this->saveFirstPage($records['reviews']);
            }

            $this->activePage++;

            if (strlen($records['reviews']) === self::EMPTY_RECORD_LENGTH) {
                $records = $this->getMetaRecord();
                $this->isEnd = true;
            }
        } else {
            $records = $this->getEndCode();
        }
        return $records;
    }

    private function parseHandledSource() {
        if ($this->isReadyQueue === false) {
            $records = $this->getReviews(self::FIRST_PAGE);

            if ($this->isEqualsHash(md5($records)) === false) {
                $this->queue [] = $records;
                $this->saveFirstPage($records['reviews']);
            }

            $this->queue [] = $this->getMetaRecord();
            $this->queue [] = $this->getEndCode();

            $this->isReadyQueue = true;
        }
        return array_shift($this->queue);
    }

    private function getReviews($page) {
        $this->queryInfo['page'] = $this->activePage;
        $addQuery = http_build_query($this->queryInfo);

        $records['reviews'] = Request::get(self::HOST . '&' . $addQuery)->body;
        $records['type'] = self::TYPE_REVIEWS;

        return $records;
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

    private function getMetaRecord() {
        return $this->metaRecord;
    }

    private function getEndCode() {
        return self::END_CODE;
    }

    private function isEqualsHash(string $md5): bool {
        return $this->oldHash === $md5;
    }

    private function saveFirstPage($records) {
        $this->metaRecord->hash = md5($records);
    }
}