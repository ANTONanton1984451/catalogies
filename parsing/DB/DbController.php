<?php
// todo: singletone?
// todo: Создать отдельный конфиг, через который будет происходит подключение к БД
// todo: Подумать над добавлением onUpdate реакций для внешних ключей
// todo: Refactor getActualSourceReview
// todo: Да и в целом, явно требуется рефакторинг и отладка данного класса
// todo: В методе insertReviews, требуется дополнительно заносить в конфиг последний хэш, рейтинг площадки и остальную
//          информацию

namespace parsing;

use Medoo\Medoo;
use PDO;

class DbController
{
    private $database;

    public function __construct() {
        $this->database = $this->getConnection();
    }

    public function insertSourceReview(array $source_review) {
        $this->database->insert('source_review', $source_review);
    }

    public function updateSourceReview() {
    }

    public function getSourceReview() {}

    public function getActualSourceReview() {
        return $this->database->select("source_review", "*");
    }
    public function deleteSourceReview() {}

    public function insertReviews(array $reviews, $source_hash) {
        foreach ($reviews as $review) {
            $review['source_hash_key']  =   $source_hash;

            $this->database->insert("review", array_slice($review, 1 ));

            if (isset($review['identifier'])) {
                $this->database->insert("add_info_review", [
                    'identifier'        =>  $review['identifier'],
                    'review_id'         =>  $this->database->id(),
                ]);
            }
        }
    }

    public function updateReview() {}

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