<?php

namespace parsing\platforms\zoon;

use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\GetterInterface;
use parsing\logger\LoggerManager;
use Exception;
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

    /**
     * Данная функция выполняется после инициализации объекта, и задает значения для полей, а также
     * вызывает генерацию мета-данных о ссылке (кол-во отзывов, общая оценка организации-заведения).
     *
     * @param $config array
     */
    public function setConfig($config) {

        // todo: Если в записи $config не хватает данных для обработки - exception, logger

        $this->handled = $config['handled'];
        $this->metaRecord = $this->generateMetaRecord($config['source']);

        if ($this->handled === self::HANDLED_TRUE) {
            $this->sourceConfig = json_decode($config["config"], true);
        }
    }

    /**
     * Функция выбирает необходимые метод обработки ссылки, и возвращает результат в Parser.
     *
     * @return array|object
     */

    public function getNextRecords(){
        if ($this->handled === 'HANDLED') {
            $records = $this->parseHandledSource();
        } else {
            $records = $this->parseNewSource();
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

    /**
     * Функция парсит новые ссылки. Возвращает сначала все возможные отзывы.
     * После этого возвращает мета-данные ссылки.
     * В конце возвращает end-code.
     *
     * @return object|array|int
     */
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

    /**
     * Функция пытается получить закодированную json строку с отзывами.
     * В случае, если отзывы получены частично, или не получены вовсе, происходит откат всех отзывов, на ссылку вешается
     * флаг ошибки, и она откладывается в очередь для отдельного воркера.
     *
     * @return string
     */
    private function getReviews() {
        $this->addQueryParameters['skip'] = $this->activePage++ * self::REVIEWS_LIMIT;
        $addQuery = http_build_query($this->addQueryParameters);
        return file_get_contents(self::QUERY_CONSTANT_PARAMETERS . '&' . $addQuery);
    }

    /**
     * Возвращает массив с мета-данными ссылки
     *
     * @return array
     */
    private function getMetaRecord() {
        return $this->metaRecord;
    }

    /**
     * Возвращает END_CODE, который сигнализирует объекту Parser о том, что необходимо закончить работу.
     *
     * @return int
     */
    private function getEndCode() {
        return self::END_CODE;
    }

    /**
     * Данная фунция получает id организации, который требуется при запросе отзывов,
     * а также сохраняет мета информацию о месте.
     *
     * @param $source
     * @return mixed
     */
    private function generateMetaRecord($source) {
        // todo: ? Можно добавить в ассоциативный массив поле 'status' => meta, для того, чтобы различать сообщения

        $file = file_get_contents($source);
        $document = phpQuery::newDocument($file);

        // todo: Создать проверку, на получение токена. В случае, если не получается, закончить работу парсера,
        //          и сгенерировать сообщение в логгере.

        $this->addQueryParameters['organization'] = $document
            ->find('.comments-section')->attr('data-owner-id');

        // todo: Если не удалось получить мета данные - exception, logger

        $countReviews = $document->find('.fs-large.gray.js-toggle-content')->text();
        $metaRecord['count_reviews'] = explode(' ', trim($countReviews))[0];
        $metaRecord['average_mark'] = $document->find('span.rating-value')->text();

        phpQuery::unloadDocuments();

        return $metaRecord;
    }

    /**
     * @param $hash
     * @return bool
     */
    private function isEqualsHash($hash) {
        return $hash === $this->sourceConfig['old_hash'];
    }
}