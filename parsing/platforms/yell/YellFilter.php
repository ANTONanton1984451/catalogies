<?php

namespace parsing\platforms\yell;

use parsing\factories\factory_interfaces\FilterInterface;
use phpQuery;

class YellFilter implements FilterInterface {

    public function clearData($records) {

        if ($records->type === self::TYPE_REVIEWS) {
            $doc = phpQuery::newDocument($records->body);
            $reviews = $doc->find('div.reviews__item');

            foreach ($reviews as $review) {
                $phpQueryObject = pq($review);

                $result [] = [
                    'identifier' => $this->setIdentifier($phpQueryObject),
                    'text' => $this->setText($phpQueryObject),
                    'date' => $this->setDate($phpQueryObject),
                    'rating' => $rating = $this->setRating($phpQueryObject),
                    'tonal' => $this->setTonal($rating),
                    'is_answered' => $this->setIsAnswered($phpQueryObject),
                ];
            }

            $records = $result;
        }

        return $records;
    }

    private function setIdentifier($phpQueryObject) {
        return $phpQueryObject->find('div.reviews__item-user-name')->text();
    }

    private function setText($phpQueryObject) {
        return $phpQueryObject->find('div.reviews__item-text')->text();
    }

    private function setDate($phpQueryObject) {
        return strtotime($phpQueryObject->find('span.reviews__item-added')->attr('content'));
    }

    private function setRating($phpQueryObject) {
        return (int) $phpQueryObject->find('span.rating__value')->text();
    }

    private function setTonal(int $rating) : string {
        if ($rating === 5) {
            $result = 'POSITIVE';
        } elseif ($rating === 4) {
            $result = 'NEUTRAL';
        } else {
            $result = 'NEGATIVE';
        }

        return $result;
    }

    private function setIsAnswered($phpQueryObject) {
        $answers = $phpQueryObject->find('div.replies__item');

        $isAnswered = false;

        if (!empty($answers)) {
            $organizationName = $phpQueryObject->find('span[itemprop=name]')->text();

            foreach ($answers as $answer) {
                $phpQueryTempObject = pq($answer);
                $respondentName = $phpQueryTempObject->find('b')->text();

                if ($respondentName === $organizationName) {
                    $isAnswered = true;
                }
            }
        }

        return $isAnswered;
    }
}