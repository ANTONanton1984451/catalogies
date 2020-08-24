<?php
// todo: singletone?
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

    public function __construct() {
        $this->database = $this->getConnection();
    }

    public function getActualSources(int $limit):array {
        $dates = $this->calcPriorityDates();
        $now = $dates['fourHoursAgo'];
        $fourHoursAgo = $dates['minTime'];
        return $this->database->query("SELECT ($now -`last_parse_date`) + `review_per_day` as priority,
                                                      `source_hash_key` as source
                                                      FROM `task_queue`
                                                      WHERE `last_parse_date` < $fourHoursAgo
                                                      ORDER BY priority DESC
                                                      LIMIT $limit")
                                                      ->fetchAll(\PDO::FETCH_ASSOC);

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

    public function updateSourceReview($source_hash, $updateData) {
        $this->database->update("source_review", $updateData, ["source_hash"=>$source_hash]);
    }

    public function getSourceReview($source_hash) {
        $this->database->select("source_review", "*", ["source_hash" => $source_hash]);
    }

    public function deleteSourceReview() {}

    private function calcPriorityDates():array
    {
        $nowTimeSeconds = time();
        $nowTimeHours = round($nowTimeSeconds / 3600);
        $fourHoursAgo = $nowTimeHours - 4;
        return [
                'nowTime' => $nowTimeHours,
                'fourHoursAgo' => $fourHoursAgo
               ];
    }

    private function getConnection() {
        return new Medoo([
            'database_type'     => 'mysql',
            'database_name'     => 'test',
            'server'            => 'localhost',
            'username'          => 'root',
            'password'          => '',
            'option'            => [
                PDO::ATTR_ERRMODE   =>  PDO::ERRMODE_EXCEPTION
            ]
        ]);
    }

}