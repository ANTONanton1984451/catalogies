<?php

// todo: Продумать варианты проверки, и запасные варианты, на случай изменений на сайте, в API и т.д.

// todo: ZOON, FLAMP, YELL -> если создавать документ для проверки даты последнего отзыва,
//          можно существенно ускорить время работы парсера при новых ссылках

namespace parsing\platforms\zoon;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\GetterInterface;
use parsing\logger\LoggerManager;
use Unirest\Request;
use stdClass;
use phpQuery;

class ZoonGetter implements GetterInterface {

    const REVIEWS_LIMIT = 102;
    const FIRST_PAGE = 0;

    const QUERY_CONSTANT_PARAMETERS = "https://zoon.ru/js.php?area=service&action=CommentList&owner[]=organization&" .
    "is_widget=1&strategy_options[with_photo]=0&allow_comment=0&allow_share=0&limit=" . self::REVIEWS_LIMIT;

    private $addQueryParameters;
    private $sourceStatus;
    private $metaRecord;
    private $oldHash;

    private $isReadyQueue = false;
    private $queue;

    private $isEnd = false;
    private $activePage = 0;

    public function setConfig($config) {
        if ($this->validateConfig($config) === true) {
            $this->sourceStatus = $config['handled'];
            $this->metaRecord = $this->generateMetaRecord($config["source"], $config["source_hash"]);

            if ($this->sourceStatus === self::SOURCE_HANDLED) {
                $this->oldHash = json_decode($config["config"])->old_hash;
            }

        } else {
            $this->sourceStatus = self::SOURCE_UNPROCESSABLE;
            (new DatabaseShell())->updateSourceReview($config['source_hash'], ['handled' => 'UNPROCESSABLE']);

            $message = "Недостаточно данных для обработки ссылки";
            LoggerManager::log(LoggerManager::DEBUG, $message, [$config]);
        }
    }

    /**
     * Данная фунция получает id организации, который требуется при запросе отзывов,
     * а также сохраняет мета информацию о месте.

     * @param $source string
     * @param $sourceHash string
     * @return mixed
     */
    private function generateMetaRecord (string $source, string $sourceHash) {
        $response = Request::get($source);
        $document = phpQuery::newDocument($response->body);

        $organizationId = $document->find('#reviews div')->attr('data-owner-id');

        if ($organizationId == "") {
            $message = "Не удалось получить токен заведения";
            LoggerManager::log(LoggerManager::DEBUG, $message, [$source]);

            $this->sourceStatus = self::SOURCE_UNPROCESSABLE;
            (new DatabaseShell())->updateSourceReview($sourceHash, ['handled' => 'UNPROCESSABLE']);

        } else {
            $this->addQueryParameters['organization'] = $organizationId;
        }

        $countReviews = $document->find('.fs-large.gray.js-toggle-content')->text();

        $metaRecord = new stdClass();
        $metaRecord->type = self::TYPE_METARECORD;
        $metaRecord->count_reviews = explode(' ', trim($countReviews))[0];
        $metaRecord->average_mark = $document->find('span.rating-value')->text();

        phpQuery::unloadDocuments();

        return $metaRecord;
    }

    /**
     * Функция выбирает необходимые метод обработки ссылки, и возвращает результат в Parser.

     * @return object|int
     */
    public function getNextRecords(){
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


    /**
     * Функция получает все отзывы по данной ссылке, и передаёт их далее.
     * После этого получает и передаёт мета-данные ссылки.
     * В конце передаёт END_CODE, который сигнализирует об окончании работы объекта Parser.

     * @return object|int
     */
    private function parseNewSource () {
        if ($this->isEnd != true) {
            $records = $this->getReviews($this->activePage);

            if ($this->activePage === self::FIRST_PAGE) {
                $this->saveFirstPage($records->list);
            }

            $this->activePage++;

            if ($records->list === "") {
                $records = $this->getMetaRecord();
                $this->isEnd = true;
            }
        } else {
            $records = $this->getEndCode();
        }
        return $records;
    }

    /**
     * Функция получает отзывы по данной ссылке, и сравнивает их с полученными ранее.
     * Если присутствуют изменения, то передаются отзывы, иначе этот шаг пропускается.
     * После этого получает и передаёт мета-данные ссылки.
     * В конце передаёт END_CODE, который сигнализирует об окончании работы объекта Parser.

     * @return object|int
     */
    private function parseHandledSource () {
        if ($this->isReadyQueue === false) {
            $records = $this->getReviews(self::FIRST_PAGE);

            if ($this->isEqualsHash(md5($records->list)) === false) {
                $this->queue [] = $records;
                $this->saveFirstPage($records->list);
            }

            $this->queue [] = $this->getMetaRecord();
            $this->queue [] = $this->getEndCode();

            $this->isReadyQueue = true;
        }
        return array_shift($this->queue);
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

    private function getReviews(int $page) : object{
        $this->addQueryParameters['skip'] = $page * self::REVIEWS_LIMIT;
        $addQuery = http_build_query($this->addQueryParameters);

        $records = Request::get(self::QUERY_CONSTANT_PARAMETERS . '&' . $addQuery)->body;
        $records->type = self::TYPE_REVIEWS;

        return $records;
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

    private function saveFirstPage($records) {
        $this->metaRecord->hash = md5($records);
    }
}