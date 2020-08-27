<?php
// todo: singleton?
// todo: Подумать над добавлением onUpdate реакций для внешних ключей
// todo: Refactor getActualSourceReview
// todo: Да и в целом, явно требуется рефакторинг и отладка данного класса
// todo: Создать методы для работы с конфигами

namespace parsing\DB;

use Medoo\Medoo;
use PDO;

class DatabaseShell
{
    private $database;

    public function __construct()
    {
        $this->database = $this->getConnection();
    }

    /**
     * @param int $limit
     * @param array $platforms
     * @return array
     * Площадки перечисляем в таком формате : ["'площадка'","'площадка'"]
     */
    public function getActualSources(int $limit, array $platforms): array
    {
        $platformsSql = implode(",", $platforms);
        $dates = $this->calcPriorityDates();

        $now = $dates['nowTime'];
        $fourHoursAgo = $dates['fourHoursAgo'];

        return $this->database->query("
            SELECT ($now -`last_parse_date`) + `review_per_day` as priority,
                `task_queue`.`source_hash_key` as hash,
                `source_config` as config,
                `source`
                `track`,
                `platform`
            FROM `task_queue`
            JOIN `source_review`
            ON task_queue.source_hash_key = source_review.source_hash
            WHERE `last_parse_date` < $fourHoursAgo
            AND platform IN($platformsSql)
            AND actual = 'ACTIVE'
            ORDER BY priority DESC
            LIMIT $limit
            ")
            ->fetchAll(\PDO::FETCH_ASSOC);

    }

    public function getNewSources(int $limit): array
    {
        return $this->database->select('source_review', [
            'source_hash',
            'platform',
            'source_config',
            'track',
            'source',
            'handled'
        ],
        [
//            'handled' => 'NEW',
            "LIMIT" => $limit
        ]);
}

    // Work with Review
    public function insertReviews(array $reviews, array $constInfo = [])
    {
        foreach ($reviews as $review) {
            $this->database->insert("review", array_merge($review, $constInfo));
        }
    }

    // Work with Source Review
    public function insertSourceReview(array $source_review)
    {
        $this->database->insert('source_review', $source_review);
    }

    public function updateSourceReview($source_hash, $updatedRecords)
    {
        $this->database->update("source_review", $updatedRecords, ["source_hash" => $source_hash]);
    }

    public function getSourceReview($source_hash)
    {
        $this->database->select("source_review", "*", ["source_hash" => $source_hash]);
    }

    private function calcPriorityDates(): array
    {
        $nowTimeSeconds = time();
        $nowTimeHours = round($nowTimeSeconds / 3600);
        $fourHoursAgo = $nowTimeHours - 4;
        return [
            'nowTime' => $nowTimeHours,
            'fourHoursAgo' => $fourHoursAgo
        ];
    }

    private function getConnection()
    {
        return new Medoo([
            'database_type' => 'mysql',
            'database_name' => 'test',
            'server' => 'localhost',
            'username' => 'borland',
            'password' => 'attache1974',
            'option' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]
        ]);
    }
}