<?php

// todo: singleton?
// todo: Можно добавить поле isAnswered в review
// todo: Вынести конфиг с БД из git-репозитория, чтобы можно было не тыркать его постоянно

namespace parsing\DB;

use Medoo\Medoo;
use parsing\factories\factory_interfaces\ConstantInterfaces;
use PDO;

class DatabaseShell implements ConstantInterfaces
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
    public function getSources(int $limit , string $handled_flag , array $platforms = []) : array
    {
        switch ($handled_flag){
            case self::SOURCE_NEW:
            case self::SOURCE_NON_COMPLETED:
            case self::SOURCE_NON_UPDATED:
                $sources = $this->getNonHandledSources($limit,$handled_flag);
                break;
            case self::SOURCE_HANDLED:
                $sources = $this->getHandledSources($limit,$platforms);
                break;
        }
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
    public function getHandledSources(int $limit, array $platforms): array
    {
        $platformsSql = implode(",", $platforms);
        $dates = $this->calcPriorityDates();

        $now = $dates['nowTime'];
        $fourHoursAgo = $dates['fourHoursAgo'];

        return $this->database->query("
            SELECT ($now -`last_parse_date`) + `review_per_day` as priority,
                `task_queue`.`source_hash_key` as source_hash,
                `source_config` as config,
                `handled`,
                `source`,
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
     * @param string $handled_flag
     * @return array
     */
    public function getNonHandledSources(int $limit,string $handled_flag) : array
    {
        return $this->database->select('source_review', [
            'source_hash',
            'platform',
            'track',
            'source',
            'handled',
            'source_config(config)'
        ],
        [
            'handled' => $handled_flag,
            'actual' =>self::SOURCE_ACTUAL,
            "LIMIT" => $limit
        ]);
    }

    // Work with Review
    public function insertReviews(array $reviews, array $constInfo = []):int
    {
        foreach ($reviews as &$review) {
            $review = array_merge($review,$constInfo);
        }

       return $this->database->insert("review", $reviews)->rowCount();
    }

    // Work with Source Review
    public function insertSourceReview(array $source_review):int
    {
      return  $this->database->insert('source_review', $source_review)->rowCount();
    }

    public function updateSourceReview($source_hash, $updatedRecords):int
    {
      $pdoStatement = $this->database->update("source_review", $updatedRecords, ["source_hash" => $source_hash]);
      return $pdoStatement->rowCount();
    }

    /**
     * Функция производит откат отзывов по какой либо ссылке,
     * если не имеется возможности гарантировать целостность данных
     *
     * @param string $source_hash_key
     * @param string $status
     * @return void
     */
    public function rollback(string $source_hash_key,string $status):void
    {
        $this->database->delete("review", ['source_hash_key' => $source_hash_key]);
        $this->updateSourceReview($source_hash_key,['handled'=>$status]);
    }


    public function insertTaskQueue(array $task):int {
        return $this->database->insert("task_queue", $task)->rowCount();
    }

    public function updateTaskQueue($source_hash_key, array $task) : int {
      $pdoStatement = $this->database->update("task_queue", $task, ["source_hash_key" => $source_hash_key]);
      return $pdoStatement->rowCount();
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
    private function getConnection() {
        return new Medoo([
            'database_type' => 'mysql',
            'database_name' => DATABASE,
            'server' => 'localhost',
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'option' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]
        ]);
    }
}