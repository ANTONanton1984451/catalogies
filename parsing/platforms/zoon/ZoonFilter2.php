<?php

namespace parsing\platforms\zoon;

use parsing\factories\factory_interfaces\FilterInterface;
use phpQuery;

class ZoonFilter2 implements FilterInterface
{

    const FORMAT_HARD   = 1;
    const FORMAT_MIX    = 2;
    const FORMAT_SIMPLE = 3;

    private $format;

    private $config;
    private $document;

    public function clearData($buffer) {
        if (is_object($buffer)) {
            $buffer = $this->handlingReviews($buffer);
        }

        return $buffer;
    }

    private function handlingReviews($buffer) {
        $this->document = phpQuery::newDocument($buffer->list);
        $this->checkFormat();
        // todo: cutData?

        switch ($this->format) {
            case self::FORMAT_MIX:
                $buffer = $this->handleMixReview($buffer);
                break;

            case self::FORMAT_HARD:
                $buffer = $this->handleHardReview();
                // no break

            case self::FORMAT_SIMPLE:
                $buffer = $this->handleSimpleReview($buffer);
        }

        return $buffer;
    }

    private function checkFormat() {
        if ($this->document->find('.comment-container.js-comment-container')->text() === '') {
            $this->format = self::FORMAT_HARD;
        } elseif ($this->document->find('script')->text() === '') {
            $this->format = self::FORMAT_SIMPLE;
        } else {
            $this->format = self::FORMAT_MIX;
        }
    }

    public function setConfig($config) {
        $this->config = $config;
    }

    private function handleHardReview() {
        $reviews = $this->document->find('script');

        foreach ($reviews as $review) {
            $pq = pq($review);
            $simpleReview = explode('"' ,$pq->text())[1];

            $simpleReview = str_replace("A", "@", $simpleReview);
            $simpleReview = str_replace("=", "A", $simpleReview);
            $simpleReview = str_replace("@", "=", $simpleReview);

            $result[] = base64_decode($simpleReview);
        }

        return $result;
    }

    private function handleSimpleReview() {
        $this->document->find('ul')->remove();
        $reviews = $this->document->find('li');

        foreach ($reviews as $review) {
            $pq = pq($review);

            $date = $pq->find('.iblock.gray')->text();
            var_dump($date);
            exit();
        }
    }
}