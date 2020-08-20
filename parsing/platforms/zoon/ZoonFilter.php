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

    public function clearData($buffer): array
    {
        if (is_object($buffer)) {
            $buffer = $this->handlingReviews($buffer);
        }

        return $buffer;
    }

    private function handlingReviews($buffer): array
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

    private function formatDate($date): int
    {
        $split_date = preg_split("/\s+/", $date);

        if (isset($split_date[9])) {
            $split_date[11] = $this->swapMonthFormat($split_date[11]);
            $result = array_slice($split_date, 10, 3);
            $result[] = $split_date[14];
        } else {
            $split_date[2] = $this->swapMonthFormat($split_date[2]);
            $result = array_slice($split_date, 1, 3);
            $result[] = $split_date[5];
        }

        return strtotime(implode($result, '-'));
    }

    private function swapMonthFormat(string $month): string
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

    public function setConfig($config){}
}