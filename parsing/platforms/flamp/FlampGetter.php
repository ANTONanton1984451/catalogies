<?php

// todo: Проблематика. Генерация старого хэша происходит не очень очевидно. Стоит обратить внимание, и вынести его
//          в отдельную функцию

// todo: Придумать, куда перенести типы записи, для того, чтобы изменения этого механизма в одном месте, не создавало
//          ошибки в других местах

// todo: Переписать getReviews

namespace parsing\platforms\flamp;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\GetterInterface;
use parsing\logger\LoggerManager;
use Unirest\Request;
use stdClass;
use phpQuery;

class FlampGetter implements GetterInterface
{
    const API_URL_PREFIX = 'https://flamp.ru/api/2.0/filials/';
    const API_URL_POSTFIX = '/reviews?limit=' . self::LIMIT_REVIEWS;

    const HEADERS = [
        "Accept" => ";q=1;depth=1;scopes={\"user\":{\"fields\":\"id,name,url,image,reviews_count,sex\"},\"official_answer\":{}};application/json",
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

    private $status;
    private $metaRecord;
    private $oldHash;

    private $organizationId;
    private $nextLink = 0;
    private $halfYearAgo;

    private $isEnd = false;
    private $isReviewsSended = false;

    const LIMIT_REVIEWS = 5;
    const HALF_YEAR_TIMESTAMP = 15724800;

    const FIRST_RECORDS = true;
    const OTHER_RECORDS = false;

    const TYPE_REVIEWS = 'reviews';
    const TYPE_METARECORD = 'meta';

    /**
     * Данная функция выполняется после инициализации объекта, и задает значения для полей, а также
     * вызывает генерацию мета-данных о ссылке (кол-во отзывов, общая оценка организации-заведения).
     *
     * @param $config
     */
    public function setConfig($config) {
        $this->metaRecord = new stdClass();

        if ($this->validateConfig($config)) {
            $this->status = $config['handled'];
            $this->halfYearAgo = time() - self::HALF_YEAR_TIMESTAMP;
            $this->metaRecord = $this->generateMetaRecord($config['source'], $config['source_hash']);

            if ($this->status === self::SOURCE_HANDLED) {
                $this->oldHash = json_decode($config["config"], true)['old_hash'];
            }
        }
    }

    private function validateConfig($config) {
        $response = Request::get($config['source']);

        $incorrectHttpCode = $response->code != 200;
        $handledNotExist = !array_key_exists("handled", $config);
        $trackNotExist = !array_key_exists("track", $config);

        if ($incorrectHttpCode || $handledNotExist || $trackNotExist) {
            $this->status = self::SOURCE_UNPROCESSABLE;
            (new DatabaseShell())->updateSourceReview($config['source_hash'], ['handled' => 'UNPROCESSABLE']);

            $message = "Недостаточно данных для обработки ссылки";
            LoggerManager::log(LoggerManager::DEBUG, $message, [$config]);

            return false;
        }

        return true;
    }

    private function generateMetaRecord($source, $source_hash) {
        $response = Request::get($source);
        $document = phpQuery::newDocument($response->body);

        $this->organizationId = $document->find('cat-brand-cover')->attr('entity-id');

        if ($this->organizationId == "") {
            $message = "Не удалось получить токен заведения";
            LoggerManager::log(LoggerManager::DEBUG, $message, [$source]);

            $this->status = self::SOURCE_UNPROCESSABLE;
            (new DatabaseShell())->updateSourceReview($source_hash, ['handled' => 'UNPROCESSABLE']);
        }

        $countReviews = $document->find('div.filial-rating__value.filial-rating__value--5')->text();
        $averageMark = $document->find('div.hg-row__side.t-h3')->text();

        $metaRecord = new stdClass();
        $metaRecord->count_reviews = trim($countReviews);
        $metaRecord->average_mark = trim($averageMark);

        return $metaRecord;
    }

    public function getNextRecords() {
        switch ($this->status) {
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
            if ($this->nextLink === 0) {
                $records = $this->getReviews(self::FIRST_RECORDS);
            } else {
                $records = $this->getReviews(self::OTHER_RECORDS);
            }

            $records->type = self::TYPE_REVIEWS;

            // todo: После срабатывания isOverHalfYear нужно сначала доставить отзывы, а потом еще одна итерация

            if ($this->isOverHalfYear($records)) {
                $records = $this->getMetaRecord();
                $records->type = self::TYPE_METARECORD;
                $this->isEnd = true;
            }

        } else {
            $records = $this->getEndCode();
        }

        return $records;
    }

    private function parseHandledSource() {
        if ($this->isEnd != true) {
            $records = $this->getReviews(true);

            if ($this->isEqualsHash(md5(json_encode($records))) || $this->isReviewsSended == true) {
                $records = $this->getMetaRecord();
                $records['type'] = self::TYPE_METARECORD;
                $this->isEnd = true;

            } else {
                $records['type'] = self::TYPE_REVIEWS;
                $this->isReviewsSended = true;
            }

        } else {
            $records = $this->getEndCode();
        }

        return $records;
    }

    private function getReviews(bool $isFirst = false) : object {
        if ($isFirst === true) {
            $apiURL = self::API_URL_PREFIX . $this->organizationId . self::API_URL_POSTFIX;
            $response = Request::get($apiURL, self::HEADERS);
            $this->metaRecord->hash = md5(json_encode($response->body));

        } else {
            $response = Request::get($this->nextLink, self::HEADERS);
        }

        $records = $response->body;
        $this->nextLink = $response->body->next_link;

        return $records;
    }

    private function getMetaRecord() {
        return $this->metaRecord;
    }

    private function getEndCode() {
        return self::END_CODE;
    }

    private function isEqualsHash($hash) {
        return $this->oldHash === $hash;
    }

    private function isOverHalfYear(object $records) {
        $countReviews = count($records->reviews);
        $lastReviewDate = strtotime($records->reviews[$countReviews - 1]->date_created);

        if ($lastReviewDate < $this->halfYearAgo) {
            return true;
        }

        return false;
    }
}