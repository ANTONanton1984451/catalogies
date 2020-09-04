<?php

namespace parsing\platforms\zoon;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\GetterInterface;
use parsing\logger\LoggerManager;
use Unirest\Request;
use phpQuery;

// todo: Проверять раз в сутки результаты по одному парсеру при помощи безголового браузера

class ZoonGetter implements GetterInterface {

    private $status;
    private $oldHash;

    private $metaRecord;

    private $isEnd = false;
    private $isReviewsSended = false;

    private $activePage = 0;

    const EMPTY = '';

    const TYPE_REVIEWS = 'reviews';
    const TYPE_METARECORD = 'meta';

    const REVIEWS_LIMIT = 102;

    const QUERY_CONSTANT_PARAMETERS = "https://zoon.ru/js.php?area=service&action=CommentList&owner[]=organization&" .
        "is_widget=1&strategy_options[with_photo]=0&allow_comment=0&allow_share=0&limit=" . self::REVIEWS_LIMIT;

    private $addQueryParameters = [
        'owner[]' => 'prof',
        'organization' => self::EMPTY,
        'skip' => self::EMPTY,
    ];


    /**
     * Данная функция выполняется после инициализации объекта, и задает значения для полей, а также
     * вызывает генерацию мета-данных о ссылке (кол-во отзывов, общая оценка организации-заведения).
     *
     * @param $config
     */
    public function setConfig($config) {

        if ($this->validateConfig($config)) {
            $this->status = $config['handled'];
            $this->metaRecord = $this->generateMetaRecord($config['source'], $config['source_hash']);

            if ($this->status === self::STATUS_HANDLED) {
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

            $this->status = self::STATUS_UNPROCESSABLE;
            (new DatabaseShell())->updateSourceReview($config['source_hash'], ['handled' => 'UNPROCESSABLE']);
            LoggerManager::log(LoggerManager::DEBUG, "Недостаточно данных для обработки ссылки", [$config]);

            return false;
        }

        return true;
    }


    /**
     * Данная фунция получает id организации, который требуется при запросе отзывов,
     * а также сохраняет мета информацию о месте.
     *
     * @param $source
     * @param $source_hash
     * @return mixed
     */
    private function generateMetaRecord($source, $source_hash) {

        $response = Request::get($source);
        $document = phpQuery::newDocument($response->body);

        $organizationId = $document->find('.comment-section')->attr('data-owner-id');

        if ($organizationId == "") {
            $message = "Не удалось получить токен заведения";
            LoggerManager::log(LoggerManager::DEBUG, $message, [$source]);

            $this->status = self::STATUS_UNPROCESSABLE;
            (new DatabaseShell())->updateSourceReview($source_hash, ['handled' => 'UNPROCESSABLE']);

        } else {
            $this->addQueryParameters['organization'] = $organizationId;
        }

        $countReviews = $document->find('.fs-large.gray.js-toggle-content')->text();
        $metaRecord['count_reviews'] = explode(' ', trim($countReviews))[0];
        $metaRecord['average_mark'] = $document->find('span.rating-value')->text();

        phpQuery::unloadDocuments();

        return $metaRecord;
    }


    /**
     * Функция выбирает необходимые метод обработки ссылки, и возвращает результат в Parser.
     *
     * @return array|object
     */
    public function getNextRecords(){

        switch ($this->status) {
            case self::STATUS_NEW:
                $records = $this->parseNewSource();
                break;

            case self::STATUS_HANDLED:
                $records = $this->parseHandledSource();
                break;

            case self::STATUS_UNPROCESSABLE:
                $records = $this->getEndCode();
                break;
        }

        return $records;
    }


    /**
     * Функция парсит уже ранее обработанные ссылки. Возвращает сначала отзывы, если появились новые отзывы.
     * После этого возвращает мета-данные ссылки.
     * В конце возвращает end-code.
     *
     * @return object|array|int
     */
    private function parseHandledSource() {
        if ($this->isEnd != true) {
            $records = $this->getReviews();

            if ($this->isEqualsHash(md5($records)) || $this->isReviewsSended == true) {
                $records = $this->getMetaRecord();
                $records['type'] = self::TYPE_METARECORD;
                $this->isEnd = true;

            } else {
                $records = json_decode($records);
                $records['type'] = self::TYPE_REVIEWS;
                $this->isReviewsSended = true;
            }

        } else {
            $records = $this->getEndCode();
        }

        return $records;
    }


    /**
     * Функция парсит новые ссылки. Возвращает сначала все возможные отзывы.
     * После этого возвращает мета-данные ссылки.
     * В конце возвращает end-code.
     *
     * @return object|array|int
     */
    private function parseNewSource() {
        if ($this->isEnd != true) {
            $records = $this->getReviews();

            if ($this->activePage == 1) {
                $this->metaRecord['hash'] = md5($records);
            }

            $records = json_decode($records, true);
            $records['type'] = self::TYPE_REVIEWS;

            if ($records['list'] == self::EMPTY) {
                $records = $this->getMetaRecord();
                $records['type'] = self::TYPE_METARECORD;
                $this->isEnd = true;
            }

        } else {
            $records = $this->getEndCode();
        }

        return $records;
    }


    /**
     * Функция пытается получить закодированную json строку с отзывами.
     * В случае, если отзывы получены частично, или не получены вовсе, происходит откат всех отзывов, на ссылку вешается
     * флаг ошибки, и она откладывается в очередь для отдельного воркера.
     *
     * @return string
     */
    private function getReviews() : string {
        $this->addQueryParameters['skip'] = $this->activePage++ * self::REVIEWS_LIMIT;
        $addQuery = http_build_query($this->addQueryParameters);
        return Request::get(self::QUERY_CONSTANT_PARAMETERS . '&' . $addQuery)->body;
    }


    private function getMetaRecord() {
        return $this->metaRecord;
    }


    private function getEndCode() {
        return self::END_CODE;
    }


    private function isEqualsHash($hash) {
        return $hash === $this->oldHash;
    }
}