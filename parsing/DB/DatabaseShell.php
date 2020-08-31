<?php

// todo: singleton?

namespace parsing\DB;

use Medoo\Medoo;
use PDO;

class DatabaseShell
{
    private $database;

    public function __construct() {
        $this->database = $this->getConnection();
    }

    /**
     * Функция в зависимости от параметров, возвращает ссылки для воркеров, в зависимости от их ответственности.
     *
     * @param int $limit
     * @param string $handled_flag
     * @param array $platforms
     *
     * @return array
     */
    public function getSources(int $limit , string $handled_flag , array $platforms = []) : array {
        if($handled_flag === 'NEW'){
            $sources = $this->getNewSources($limit);
        }elseif ($handled_flag === 'HANDLED'){
            $sources = $this->getActualSources($limit,$platforms);
        }

        // todo: Рефактор данной функции, чтобы она отдавала еще ссылки "WITH_ERROR"

        return $sources;
    }

    /**
     * Функция возвращает ссылки с учетом даты последней обработки и количества отзывов
     * для каждого воркера в пределах его ответственности.
     *
     * Площадки перечисляем в таком формате : ["'площадка'","'площадка'"]
     *
     * @param int $limit
     * @param array $platforms
     * @return array
     */
    public function getActualSources(int $limit, array $platforms): array
    {
        $platformsSql = implode(",", $platforms);
        $dates = $this->calcPriorityDates();

        $now = $dates['nowTime'];
        $fourHoursAgo = $dates['fourHoursAgo'];

        return $this->database->query("
            SELECT ($now -`last_parse_date`) + `review_per_day` as priority,
                `task_queue`.`source_hash_key` as source_hash,
                `source_config` as config,
                `handled` as handled,   
                `source` as source,
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

    /**
     * Возвращает ссылки с флагом handled для New Worker'a
     *
     * @param int $limit
     * @return array
     */
    public function getNewSources(int $limit) : array {
        return $this->database->select('source_review', [
            'source_hash',
            'platform',
            'track',
            'source',
            'handled',
            'source_config(config)'
        ],
        [
            'handled' => 'NEW',
            'actual' =>'ACTIVE',
            "LIMIT" => $limit
        ]);
}

    // Work with Review
    public function insertReviews(array $reviews, array $constInfo = []) {
        foreach ($reviews as $review) {
            $this->database->insert("review", array_merge($review, $constInfo));
        }
    }

    // Work with Source Review
    public function insertSourceReview(array $source_review) {
        $this->database->insert('source_review', $source_review);
    }

    public function updateSourceReview($source_hash, $updatedRecords) {
        $this->database->update("source_review", $updatedRecords, ["source_hash" => $source_hash]);
    }

    /**
     * Функция производит откат отзывов, и изменяет параметр handled,
     * чтобы провести дальнейшую обработку данной ссылки в отдельном порядке.
     *
     * @param $source_hash_key
     */
    public function rollback($source_hash_key) {
        $this->database->delete("review", ['source_hash_key' => $source_hash_key]);
        $sourceHandled = $this->database->select("source_review", ["handled"], ["source_hash" => $source_hash_key]);

        if ($sourceHandled == "HANDLED" || $sourceHandled == "NEW") {
            $this->database->update("source_review", ["handled" => "WITH_ERROR"], ["source_hash" => $source_hash_key]);
        } elseif ($sourceHandled == "WITH_ERROR") {
            $this->database->update("source_review", ["handled" => "FATAL_ERROR"], ["source_hash" => $source_hash_key]);
        }
    }


    // Work with Task Queue
    public function insertTaskQueue(array $task) : void {
        $this->database->insert("task_queue", $task);
    }

    public function updateTaskQueue($source_hash_key, array $task) : void {
        $this->database->update("task_queue", $task, ["source_hash_key" => $source_hash_key] );
    }

    /**
     * Возвращает массив с датами, которые служат параметрами для запроса ссылок,
     * обрабатываемых более 4 часов назад
     *
     * @return array
     */
    private function calcPriorityDates(): array {
        $nowTimeSeconds = time();
        $nowTimeHours = round($nowTimeSeconds / 3600);
        $fourHoursAgo = $nowTimeHours - 4;
        return [
            'nowTime' => $nowTimeHours,
            'fourHoursAgo' => $fourHoursAgo
        ];
    }

    /** @return Medoo */
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