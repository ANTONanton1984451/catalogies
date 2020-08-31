<?php

namespace parsing\platforms\zoon;

use parsing\factories\factory_interfaces\FilterInterface;
use phpQuery;

class ZoonFilter implements FilterInterface
{
    const FORMAT_HARD = 1;
    const FORMAT_MIX = 2;
    const FORMAT_SIMPLE = 3;

    private $format;

    /**
     * Функция обрабатывает записи с отзывами, а записи с мета-данными пропускает дальше.
     *
     * @param $buffer array|object
     * @return array
     */
    public function clearData($buffer) : array
    {
        if (is_object($buffer)) {
            $buffer = $this->handlingReviews($buffer);
        }

        return $buffer;
    }

    /**
     * Функция определяет порядок обработки отзывов, в зависимости от их формата.
     *
     * @param $buffer object
     * @return array
     */
    private function handlingReviews(object $buffer) : array
    {
        $document = phpQuery::newDocument($buffer->list);
        $this->checkFormat($document);

        switch ($this->format) {
            case self::FORMAT_MIX:
                $simpleRecords = $this->handleMixReview($document);
                $simpleDoc = phpQuery::newDocument($simpleRecords);
                $buffer = $this->handleSimpleReview($simpleDoc);
                break;

            case self::FORMAT_HARD:
                $simpleRecords = $this->handleHardReview($document);
                $simpleDoc = phpQuery::newDocument($simpleRecords);
                $buffer = $this->handleSimpleReview($simpleDoc);
                break;

            case self::FORMAT_SIMPLE:
                $buffer = $this->handleSimpleReview($document);
                break;
        }

        phpQuery::unloadDocuments();

        return $buffer;
    }

    /**
     * Определяет есть ли в записи отзывов зашифрованные отзывы, и занимают ли они весь массив, либо его часть.
     *
     * @param $document
     */
    private function checkFormat($document): void
    {
        if ($document->find('.comment-container.js-comment-container')->text() === '') {
            $this->format = self::FORMAT_HARD;
        } elseif ($document->find('script')->text() === '') {
            $this->format = self::FORMAT_SIMPLE;
        } else {
            $this->format = self::FORMAT_MIX;
        }
    }

    /**
     * Расшифровывает отзывы, и собирает из результата готовый документ.
     *
     * @param $document
     * @return string
     */
    private function handleHardReview($document): string
    {
        $result = '';
        $reviews = $document->find('script');

        foreach ($reviews as $review) {
            $pq = pq($review);
            $simpleReview = explode('"', $pq->text())[1];

            $simpleReview = str_replace("A", "@", $simpleReview);
            $simpleReview = str_replace("=", "A", $simpleReview);
            $simpleReview = str_replace("@", "=", $simpleReview);

            $result = $result . base64_decode($simpleReview);
        }

        return $result;
    }

    /**
     * Находит зашифрованные отзывы, расшифровывает, и собирает вместе с обычными отзывами в единый документ.
     *
     * @param $document
     * @return string
     */
    private function handleMixReview($document): string
    {
        $result = '';

        $reviews = $document->find('li');
        foreach ($reviews as $review) {
            $result = $result . pq($review)->htmlOuter();
        }

        $result = $result . $this->handleHardReview($document);

        return $result;
    }

    /**
     * Функция из готового документа с отзывами в нормальном формате,
     *   формирует записи по каждому отзыву, для дальнейшей записи.
     *
     * @param $document
     * @return array
     */
    private function handleSimpleReview($document): array
    {
        $document->find('ul')->remove();
        $reviews = $document->find('li');

        foreach ($reviews as $review) {
            $pq = pq($review);

            $date = $pq->find('.iblock.gray')->text();
            $date = $this->formatDate($date);

            $pq->find('.js-comment-short-text.comment-text span.js-comment-splitmarker')->remove();
            $short_text = $pq->find('.js-comment-short-text.comment-text span')->text();
            $long_text = $pq->find('.js-comment-additional-text.hidden')->text();

            if ($long_text != '') {
                $text = $short_text . $long_text;
            } else {
                $text = $short_text;
            }

            $identifier = $pq->find('span.name')->text();

            $result[] = [
                'text' => $text,
                'date' => $date,
                'identifier' => $identifier
            ];
        }

        return $result;
    }

    /**
     * @param $date string
     * @return false|int
     */
    private function formatDate(string $date)
    {
        $split_date = preg_split("/\s+/", $date);
        $split_date[2] = $this->swapMonthFormat($split_date[2]);
        $result = array_slice($split_date, 1, 3);
        $result[] = $split_date[5];

        return strtotime(implode($result, '-'));
    }

    /**
     * @param $month string
     * @return string
     */
    private function swapMonthFormat(string $month)
    {
        switch ($month) {
            case 'января':
                $month = '01';
                break;
            case 'февраля':
                $month = '02';
                break;
            case 'марта':
                $month = '03';
                break;
            case 'апреля':
                $month = '04';
                break;
            case 'мая':
                $month = '05';
                break;
            case 'июня':
                $month = '06';
                break;
            case 'июля':
                $month = '07';
                break;
            case 'августа':
                $month = '08';
                break;
            case 'сентября':
                $month = '09';
                break;
            case 'октября':
                $month = '10';
                break;
            case 'ноября':
                $month = '11';
                break;
            case 'декабря':
                $month = '12';
                break;
        }
        return $month;
    }
}