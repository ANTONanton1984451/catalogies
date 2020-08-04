<?php
// todo: Изучить тему, связанную с использованием прокси, и избеганием бана от площадок.

namespace parsing\platforms\zoon;


use parsing\platforms\Filter;
use phpQuery;


class ZoonFilter extends Filter
{
    const FORMAT_SIMPLE     = 0;
    const FORMAT_HARD       = 1;
    const FORMAT_MIX        = 2;

    private $format_reviews;
    private $tempReviews = [];
    private $readyReviews = [];

    private $doc;

    public function clearData($dirty_reviews)
    {
        $this->readyReviews = [];       // После каждого прохода, необходимо очистить массив

        $this->doc = phpQuery::newDocument($dirty_reviews->list);
        $this->checkFormat($dirty_reviews);
        $this->cutData();
        phpQuery::unloadDocuments();

        if ($this->format_reviews === self::FORMAT_SIMPLE) {
            $this->clearSimpleData();
        }

        if ($this->format_reviews === self::FORMAT_HARD) {
            $this->clearHardData(count($this->tempReviews));
        }

        if ($this->format_reviews === self::FORMAT_MIX) {
            $this->clearMixData();
        }

        return $this->readyReviews;
    }

    private function clearHardData($quantity) : void
    {
        for ($i = 0; $i < $quantity; $i++) {
            $temp = $this->tempReviews[$i];

            $temp = str_replace("A", "@", $temp);
            $temp = str_replace("=", "A", $temp);
            $temp = str_replace("@", "=", $temp);

            $temp = base64_decode($temp);

            $this->tempReviews[$i] = $temp;
        }

        $this->clearSimpleData();
    }

    private function clearSimpleData() : void
    {
        foreach ($this->tempReviews as $review)
        {
            $doc = phpQuery::newDocument($review);

            $date = $doc->find('.iblock.gray')->text();
            $date = $this->formatDate($date);

            $doc->find('.js-comment-short-text.comment-text span.js-comment-splitmarker')->remove();
            $text_review_short = $doc->find('.js-comment-short-text.comment-text span')->text();
            $text_review_long = $doc->find('.js-comment-additional-text.hidden')->text();

            $author = $doc->find('span.name')->text();

            if ($text_review_long != '') {
                $text_review = $text_review_short . $text_review_long;
            } else {
                $text_review = $text_review_short;
            }

            $this->readyReviews[] = [$date, $author, $text_review];

            phpQuery::unloadDocuments();

            // todo: Проверка на официальный ответ
        }

        $this->tempReviews = [];
    }

    private function clearMixData() : void
    {
        for ($i = 0; $i < count($this->tempReviews); $i++) {
            if (!preg_match('/PGxpIGRhd/', $this->tempReviews[$i])) {
                $quantity = $i - 1;
            }
        }

        $this->clearHardData($quantity);
    }

    private function formatDate($date)
    {
        $split_date = preg_split("/\s+/", $date);
        $split_date[2] = $this->swapMonthFormat($split_date[2]);    // Замена строковой записи месяца, на числовое

        $result = array_slice($split_date, 1, 3);
        $result[] = $split_date[5];

        return strtotime(implode($result, '-'));
    }

    private function swapMonthFormat($month)
    {
        switch ($month)
        {
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

    private function cutData() : void
    {
        if ($this->format_reviews === self::FORMAT_HARD || $this->format_reviews === self::FORMAT_MIX) {
            $cutter = $this->doc->find('script')->text();
            $cutter = explode('"', $cutter);

            for ($i = 1; $i < count($cutter); $i += 2) {
                $this->tempReviews[] = $cutter[$i];
            }
        }

        if ($this->format_reviews === self::FORMAT_SIMPLE || $this->format_reviews === self::FORMAT_MIX) {
            $cutter = $this->doc->find('li')->html();
            $cutter = explode('<li', $cutter);

            for ($i = 0; $i < count($cutter); $i++) {
                $this->tempReviews[] = $cutter[$i];
            }
        }
    }

    private function checkFormat() : void
    {
        if ($this->doc->find('.comment-container.js-comment-container')->text() === '') {
            $this->format_reviews = self::FORMAT_HARD;
        } elseif ($this->doc->find('script')->text() === '') {
            $this->format_reviews = self::FORMAT_SIMPLE;
        } else {
            $this->format_reviews = self::FORMAT_MIX;
        }
    }

    public function getNotifications()
    {
        // TODO: Implement getNotifications() method.
    }
}
