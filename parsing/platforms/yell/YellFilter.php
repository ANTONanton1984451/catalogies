<?php
// todo: Если это метаинформация, ее нужно пропускать

namespace parsing\platforms\yell;

use parsing\factories\factory_interfaces\FilterInterface;
use phpQuery;

class YellFilter implements FilterInterface
{
    public function clearData($raw_data)
    {
        if (!isset($raw_data['average_mark'])) {
            $doc = phpQuery::newDocument($raw_data);
            $reviews = $doc->find('div.reviews__item');

            foreach ($reviews as $review) {
                $pq = pq($review);

                $identifier = $pq->find('div.reviews__item-user-name')->text();
                $text = $pq->find('div.reviews__item-text')->text();

                $date = $pq->find('span.reviews__item-added')->attr('content');
                $date = strtotime($date);

                $rating = $pq->find('span.rating__value')->text();

                $records[] = [
                    'identifier' => $identifier,
                    'text' => $text,
                    'date' => $date,
                    'rating' => (int) $rating * 2,
                    'tonal' => $this->setTonal($rating)
                ];
            }
        }

        if (isset($raw_data['average_mark'])){
            $records = $raw_data;
        }

        return $records;
    }

    private function setTonal(string $rating) : string
    {
        if ($rating == 5) {
            $result = 'POSITIVE';
        } elseif ($rating == 4) {
            $result = 'NEUTRAL';
        } else {
            $result = 'NEGATIVE';
        }

        return $result;
    }

    public function setConfig($config){}
}