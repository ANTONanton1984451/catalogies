<?php
// todo: сделать инициализацию php query в инициализации объекта, и его unload в конце


namespace parsing\platforms\zoon;


use parsing\platforms\Filter;
use phpQuery;


class ZoonFilter extends Filter {

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

        $dirty_reviews = json_decode($dirty_reviews);
        $this->doc = phpQuery::newDocument($dirty_reviews->list);
        $this->checkFormat();

        var_dump($dirty_reviews);

        if ($this->format_reviews === self::FORMAT_SIMPLE)
        {
            $this->clearSimpleData();
            return $this->readyReviews;
        }

        if ($this->format_reviews === self::FORMAT_HARD)
        {
            $this->clearHardData();
            return $this->readyReviews;
        }

        if ($this->format_reviews === self::FORMAT_MIX)
        {
            $this->clearMixData();
            return $this->readyReviews;
        }
    }


    private function clearHardData()
    {
        $buffer = $this->doc->find('script')->text();
        $buffer = explode('"', $buffer);

        for ($i = 1; $i < count($buffer); $i += 2)
        {
            $temp = $buffer[$i];

            $temp = str_replace("A", "@", $temp);
            $temp = str_replace("=", "A", $temp);
            $temp = str_replace("@", "=", $temp);

            $temp = base64_decode($temp);

            $this->tempReviews[] = $temp;
        }

        phpQuery::unloadDocuments();

        $this->clearSimpleData();
    }

    private function clearSimpleData()
    {
        if ($this->format_reviews == self::FORMAT_SIMPLE)
        {
            $this->cutSimpleData();
        }

        foreach ($this->tempReviews as $review)
        {
            $doc = phpQuery::newDocument($review);

            $date = $doc->find('.iblock.gray')->text();
            $date = $this->formatDate($date);

            $doc->find('.js-comment-short-text.comment-text span.js-comment-splitmarker')->remove();
            $text_review_short = $doc->find('.js-comment-short-text.comment-text span')->text();
            $text_review_long = $doc->find('.js-comment-additional-text.hidden')->text();

            $author = $doc->find('span.name')->text();

            if ($text_review_long != '')
            {
                $text_review = $text_review_short . $text_review_long;
            }
            else
            {
                $text_review = $text_review_short;
            }

            $this->readyReviews[] = [$date, $author, $text_review];

            phpQuery::unloadDocuments();

            // todo: Проверка на официальный ответ

        }

        $this->tempReviews = [];
    }

    public function getNotifications()
    {
        // TODO: Implement getNotifications() method.
    }

    private function checkFormat()
    {
        if ($this->doc->find('.comment-container.js-comment-container')->text() === '')
        {
            $this->format_reviews = self::FORMAT_HARD;
        }
        elseif ($this->doc->find('script')->text() === '')
        {
            $this->format_reviews = self::FORMAT_SIMPLE;
        }
        else
        {
            $this->format_reviews = self::FORMAT_MIX;
        }
    }

    private function formatDate($date)
    {

        $split_date = preg_split("/\s+/", $date);

        switch ($split_date[2])
        {
            case 'января':
                $split_date[2] = '01';
                break;
            case 'февраля':
                $split_date[2] = '02';
                break;
            case 'марта':
                $split_date[2] = '03';
                break;
            case 'апреля':
                $split_date[2] = '04';
                break;
            case 'мая':
                $split_date[2] = '05';
                break;
            case 'июня':
                $split_date[2] = '06';
                break;
            case 'июля':
                $split_date[2] = '07';
                break;
            case 'августа':
                $split_date[2] = '08';
                break;
            case 'сентября':
                $split_date[2] = '09';
                break;
            case 'октября':
                $split_date[2] = '10';
                break;
            case 'ноября':
                $split_date[2] = '11';
                break;
            case 'декабря':
                $split_date[2] = '12';
                break;
        }

        $result = array_slice($split_date, 1, 3);
        $result[] = $split_date[5];

        $result = strtotime(implode($result, '-'));

        return $result;
    }

    private function cutSimpleData()
    {
        $temp = $this->doc->find('li.js-comment .comment-text-subtitle')->text();
        $buffer = explode('<li ', $temp);
        var_dump($temp);
    }
}
