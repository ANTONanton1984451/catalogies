<?php
// todo: Посмотреть библиотеки, которые автоматически создают реалистичные headers, для запросов

namespace parsing\platforms\flamp;

use parsing\factories\factory_interfaces\GetterInterface;
use parsing\logger\LoggerManager;
use parsing\DB\DatabaseShell;
use Unirest\Request;
use stdClass;
use phpQuery;

class FlampGetter implements GetterInterface {

    const LIMIT_REVIEWS = 50;

    const FIRST_PAGE = true;
    const OTHER_PAGE = false;

    const API_URL_PREFIX = 'https://flamp.ru/api/2.0/filials/';
    const API_URL_POSTFIX = '/reviews?limit=' . self::LIMIT_REVIEWS;

    const HEADERS = [
        "Accept" => "q=1;depth=1;scopes={\"user\":{\"fields\":\"name,url,sex\"},\"official_answer\":{}};",
        "Accept-Encoding" => "gzip, deflate, br",
        "Accept-Language" => "ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3",
        "Authorization" => "Bearer 2b93f266f6a4df2bb7a196bb76dca60181ea3b37",
        "Connection" => "keep-alive",
        "Host" => "flamp.ru",
        "Origin" => "https://novosibirsk.flamp.ru",
        "Referer" =>"https://novosibirsk.flamp.ru/",
        "User-Agent" => "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:79.0) Gecko/20100101 Firefox/79.0",
        "X-Application" => "Flamp4"
    ];

    private $sourceStatus;
    private $metaRecord;
    private $oldHash;

    private $organizationId;
    private $nextLink;

    private $isReadyQueue = false;
    private $queue;

    /**
     * Данная функция выполняется после инициализации объекта, и задает значения для полей, а также
     * вызывает генерацию мета-данных о ссылке (кол-во отзывов, общая оценка организации-заведения).
     *
     * @param $config
     */
    public function setConfig($config) : void {

        if ($this->validateConfig($config)) {
            $this->sourceStatus = $config['handled'];
            $this->metaRecord = $this->generateMetaRecord($config['source'], $config['source_hash']);

            if ($this->sourceStatus === self::SOURCE_HANDLED) {
                $this->oldHash = json_decode($config["config"], true)['old_hash'];
            }
        } else {
            $this->sourceStatus = self::SOURCE_UNPROCESSABLE;
            (new DatabaseShell())->updateSourceReview($config['source_hash'], ['handled' => 'UNPROCESSABLE']);

            $message = "Недостаточно данных для обработки ссылки";
            LoggerManager::log(LoggerManager::DEBUG, $message, [$config]);

        }
    }

    private function generateMetaRecord($source, $source_hash) {
        $response = Request::get($source);
        $document = phpQuery::newDocument($response->body);

        $this->organizationId = $document->find('cat-brand-cover')->attr('entity-id');

        if ($this->organizationId == "") {
            $message = "Не удалось получить токен заведения";
            LoggerManager::log(LoggerManager::DEBUG, $message, [$source]);

            $this->sourceStatus = self::SOURCE_UNPROCESSABLE;
            (new DatabaseShell())->updateSourceReview($source_hash, ['handled' => 'UNPROCESSABLE']);
        }

        $averageMark = $document->find('div.filial-rating__value')->text();
        $countReviews = $document->find('div.hg-row__side.t-h3')->text();

        $metaRecord = new stdClass();
        $metaRecord->count_reviews = trim($countReviews);
        $metaRecord->average_mark = trim($averageMark);
        $metaRecord->type = self::TYPE_METARECORD;

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
            $firstReviews = $this->getReviews(self::FIRST_PAGE);

            $this->saveFirstPage($firstReviews);
            $this->queue [] = $firstReviews;

            $this->queue [] = $this->getMetaRecord();
            $this->queue [] = $this->getEndCode();

            $this->isReadyQueue = true;
        }

        $records = array_shift($this->queue);

        if (is_object($records) && $records->type === self::TYPE_REVIEWS) {
            if (isset($records->body->next_link)) {
                $this->nextLink = $records->body->next_link;
            }

            if ($this->isOverHalfYear($records) === false) {
                array_push($this->queue, $this->getReviews(self::OTHER_PAGE));
            }
        }

        return $records;
    }

    private function parseHandledSource() {

        if ($this->isReadyQueue === false) {
            $records = $this->getReviews(self::FIRST_PAGE);

            if ($this->isEqualsHash($records) === false) {
                $this->queue [] = $records;
                $this->saveFirstPage($records);
            }

            $this->queue [] = $this->getMetaRecord();
            $this->queue [] = $this->getEndCode();

            $this->isReadyQueue = true;
        }

        return array_shift($this->queue);
    }

    private function getReviews(bool $isFirst = false) : object {
        $records = new stdClass();
        $records->type = self::TYPE_REVIEWS;

        if ($isFirst === true) {
            $apiURL = self::API_URL_PREFIX . $this->organizationId . self::API_URL_POSTFIX;
            $records->body = Request::get($apiURL, self::HEADERS)->body;

        } else {
            $records->body = Request::get($this->nextLink, self::HEADERS)->body;
        }

        return $records;
    }

    private function validateConfig($config) {
        $response = Request::get($config['source']);

        $incorrectHttpCode = $response->code != 200;
        $handledNotExist = !array_key_exists("handled", $config);
        $trackNotExist = !array_key_exists("track", $config);

        if ($incorrectHttpCode || $handledNotExist || $trackNotExist) {
            return false;
        }

        return true;
    }

    private function isOverHalfYear(object $records) {
        $countReviews = count($records->body->reviews);
        $lastReviewDate = strtotime($records->body->reviews[$countReviews - 1]->date_created);
        $halfYearAgo = time() - self::HALF_YEAR_TIMESTAMP;

        return $lastReviewDate < $halfYearAgo;
    }

    private function isEqualsHash(object $hash) {
        return $this->oldHash === md5(serialize($hash));
    }

    private function saveFirstPage($records) {
        $this->metaRecord->hash = md5(serialize($records));
    }

    private function getMetaRecord() {
        return $this->metaRecord;
    }

    private function getEndCode() {
        return self::END_CODE;
    }
}