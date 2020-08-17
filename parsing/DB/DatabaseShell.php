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

    public function getActualSourceReviews() {
        return $this->database->select("source_review", "*");
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

    private function getConnection() {
        return new Medoo([
            'database_type'     => 'mysql',
            'database_name'     => 'test',
            'server'            => 'localhost',
            'username'          => 'borland',
            'password'          => 'attache1974',
            'option'            => [
                PDO::ATTR_ERRMODE   =>  PDO::ERRMODE_EXCEPTION
            ]
        ]);
    }

}