<?php

namespace parsing\platforms\yell;

use parsing\factories\factory_interfaces\FilterInterface;
use phpQuery;

class YellFilter implements FilterInterface
{
    private $temp_reviews = [];

    public function clearData($raw_data) {
        $doc = phpQuery::newDocument($raw_data);
        $cutter = $doc->find('div.reviews__item');

        foreach ($cutter as $item) {
            $pq = pq($item);

            $author = $pq->find('div.reviews__item-user-name')->text();
            $text = $pq->find('div.reviews__item-text')->text();

            $date = $pq->find('span.reviews__item-added')->attr('content');
            $date = strtotime($date);

            $this->temp_reviews[] = [$author, $text, $date];
        }

        return $this->temp_reviews;
    }
}