<?php
namespace parsing\platforms\flamp;

use parsing\factories\factory_interfaces\FilterInterface;

class FlampFilter implements FilterInterface {
    public function clearData($buffer) {
        if ($buffer->type === self::TYPE_REVIEWS) {
            $buffer = $this->handlingReviews($buffer);
        }
        return $buffer;
    }

    private function handlingReviews($buffer) {
        foreach ($buffer->reviews as $record) {
            $result [] = [
                'text' => $record->text,
                'identifier' => $record->user->name,
                'rating' => $record->rating * 2,
                'tonal' => $this->setTonal($record->rating),
                'date' => strtotime($record->date_created),
                // todo: Можно расширить запись identifier, и зашифровать json
            ];
        }
        return $result;
    }

    private function setTonal(int $rating) : string {
        if ($rating == 5) {
            $result = 'POSITIVE';
        } elseif ($rating == 4) {
            $result = 'NEUTRAL';
        } else {
            $result = 'NEGATIVE';
        }
        return $result;
    }
}