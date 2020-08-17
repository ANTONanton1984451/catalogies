<?php
// todo: Изучить тему, связанную с использованием прокси, и избеганием бана от площадок.
// todo: Проверка на официальный ответ

// todo: Проверить, нужен ли массив readyRecords, и tempRecords
// todo: Проверить, необходимо ли делать приведение типов
// todo: Подумать о необходимости этого формата FORMAT_META
// todo: Сделать нормальный механизм запуска clearHardData
// todo: По возможности избавиться от регулярых выражений
// todo: Проверить, можно ли избавиться от cutData
// todo: В случае, если отзыв был отредактирован, забирать эту дату
// todo: Сделать нормальный перебор, для clearHardData
// todo: Разбить на функции clearSimpleData
// todo: Подумать над тем, что clearMixData может быть убрана за ненадобностью, либо отрефакторена
// todo: Сделать checkFormat более явным
// todo: Сделать явный вызов clear-функций друг у друга

namespace parsing\platforms\zoon;

use parsing\factories\factory_interfaces\FilterInterface;
use phpQuery;

class ZoonFilter implements FilterInterface
{
    const FORMAT_SIMPLE     = 0;
    const FORMAT_HARD       = 1;
    const FORMAT_MIX        = 2;
    const FORMAT_META       = 3;

    private $format_records;

    private $tempRecords = [];
    private $readyRecords = [];

    private $document;

    public function setConfig($config) : void {
    }

    public function clearData($raw_data) : array {
        $this->readyRecords = [];       // После каждого прохода, необходимо очистить массив

        if (is_object($raw_data)){
            // todo: Инкапсулировать все это в одну функцию, обработки отзывов
            $this->document = phpQuery::newDocument($raw_data->list);
            $this->checkFormat();
            $this->cutData();
            phpQuery::unloadDocuments();
        }

        if (is_array($raw_data)) {
            $raw_data = (object) $raw_data;
            $this->format_records = self::FORMAT_META;
        }

        switch ($this->format_records) {
            case self::FORMAT_HARD:
                $this->clearHardData(count($this->tempRecords));
                break;

            case self::FORMAT_MIX:
                $this->clearMixData();
                break;

            case self::FORMAT_SIMPLE:
                $this->clearSimpleData();
                break;

            case self::FORMAT_META:
                $this->readyRecords = $raw_data;
                break;
        }

        return $this->readyRecords;
    }

    private function clearMixData() : void {
        $quantity = 0;

        for ($i = 0; $i < count($this->tempRecords); $i++) {
            if (!preg_match('/PGxpIGRhd/', $this->tempRecords[$i])) {
                $quantity = $i - 1;
            }
        }

        $this->clearHardData($quantity);
    }

    private function clearHardData($quantity) : void {
        for ($i = 0; $i < $quantity; $i++) {
            $temp = $this->tempRecords[$i];
            $temp = str_replace("A", "@", $temp);
            $temp = str_replace("=", "A", $temp);
            $temp = str_replace("@", "=", $temp);
            $temp = base64_decode($temp);
            $this->tempRecords[$i] = $temp;
        }

        $this->clearSimpleData();
    }

    private function clearSimpleData() : void {
        foreach ($this->tempRecords as $review) {
            $doc = phpQuery::newDocument($review);
            $doc->find('ul')->remove();

            $date = $doc->find('.iblock.gray')->text();
            $date = $this->formatDate($date);

            $doc->find('.js-comment-short-text.comment-text span.js-comment-splitmarker')->remove();
            $text_review_short = $doc->find('.js-comment-short-text.comment-text span')->text();
            $text_review_long = $doc->find('.js-comment-additional-text.hidden')->text();

            $identifier = $doc->find('span.name')->text();

            if ($text_review_long != '') {
                $text_review = $text_review_short . $text_review_long;
            } else {
                $text_review = $text_review_short;
            }

            $this->readyRecords[] = [
                'text'          =>  $text_review,
                'date'          =>  $date,
                'identifier'    =>  $identifier,
            ];

            phpQuery::unloadDocuments();
        }

        $this->tempRecords = [];
    }

    private function formatDate($date) : string {
        $split_date = preg_split("/\s+/", $date);
        $split_date[2] = $this->swapMonthFormat($split_date[2]);    // Замена строковой записи месяца, на числовое

        $result = array_slice($split_date, 1, 3);
        $result[] = $split_date[5];

        return strtotime(implode($result, '-'));
    }

    private function swapMonthFormat($month) : string {
        switch ($month)  {
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

    private function cutData() : void {
        if ($this->format_records !== self::FORMAT_SIMPLE) {
            $cutter = $this->document->find('script')->text();
            $cutter = explode('"', $cutter);

            for ($i = 1; $i < count($cutter); $i += 2) {
                $this->tempRecords[] = $cutter[$i];
            }
        }

        if ($this->format_records !== self::FORMAT_HARD) {
            $this->document->find('ul')->remove();
            $cutter = $this->document->find('li');

            foreach ($cutter as $item) {
                $this->tempRecords[] = pq($item)->html();
            }
        }
    }

    private function checkFormat() : void {
        if ($this->document->find('.comment-container.js-comment-container')->text() === '') {
            $this->format_records = self::FORMAT_HARD;
        } elseif ($this->document->find('script')->text() === '') {
            $this->format_records = self::FORMAT_SIMPLE;
        } else {
            $this->format_records = self::FORMAT_MIX;
        }
    }
}
