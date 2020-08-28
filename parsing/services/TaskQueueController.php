<?php

namespace parsing\services;

use parsing\DB\DatabaseShell;

class TaskQueueController
{
    public function insertTaskQueue($countReviews, $minimalDate, $sourceHash) {
        $reviewPerDay = $countReviews / ((time() - $minimalDate) / 86400);

        if ($reviewPerDay > 6) {
            $reviewPerDay = 6 * 4;
        } elseif ($reviewPerDay < 1) {
            $reviewPerDay = 1 * 4;
        } else {
            $reviewPerDay = round($reviewPerDay) * 4;
        }

        (new DatabaseShell())->insertTaskQueue([
            'source_hash_key' => $sourceHash,
            'last_parse_date' => time() / 3600,
            'review_per_day' => $reviewPerDay,
        ]);
    }

    public function updateTaskQueue($sourceHash) {
        (new DatabaseShell())->updateTaskQueue($sourceHash, [
            'last_parse_date' => time() / 3600
        ]);
    }
}