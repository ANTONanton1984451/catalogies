<?php
namespace parsing\DB;

use Exception;
use Medoo\Medoo;
use PDO;

class MigrationsManager {
    private $database;

    const TASK_QUEUE_TABLE          = 'task_queue';
    const REVIEW_TABLE              = 'review';
    const SOURCE_REVIEW             = 'source_review';

    const SCHEMAS = [
        self::TASK_QUEUE_TABLE,
        self::REVIEW_TABLE,
        self::SOURCE_REVIEW,
    ];

    public function __construct() {
        $this->database = $this->getConnection();
    }

    public function createSchema() {
        foreach (self::SCHEMAS as $item) {
            try {
                $this->createTable($item);
            } catch (Exception $e) {
                $this->dropTables();
                break;
            }
        }

        $this->setForeignKey();
    }

    public function dropTables() {
        foreach (self::SCHEMAS as $item) {
            $this->database->drop($item);
        }
    }

    private function createTable($name) {

        switch ($name) {
            case self::SOURCE_REVIEW:
                $this->database->create(self::SOURCE_REVIEW,[
                    "source_hash"           => [
                        "VARCHAR(40)",
                        "NOT NULL",
                        "PRIMARY KEY"
                    ],
                    "platform"              => [
                        "ENUM('yandex', 'topdealers', 'google', 'zoon', 'yell', 'twogis')",
                        "NOT NULL"
                    ],
                    "source"                => [
                        "LONGTEXT",
                        "NOT NULL"
                    ],
                    "source_meta_info"      => [
                        "LONGTEXT"
                    ],
                    "source_config"         => [
                        "LONGTEXT"
                    ],
                    "actual"                => [
                        "ENUM('UNACTIVE', 'ACTIVE')",
                        "NOT NULL"
                    ],
                    "track"                 => [
                        "ENUM('ALL', 'NEGATIVE', 'NONE')",
                        "NOT NULL"
                    ],
                    "handled"               => [
                        "ENUM('NEW', 'HANDLED')",
                        "NOT NULL"
                    ]
                ]);
                break;

            case self::REVIEW_TABLE:
                $this->database->create(self::REVIEW_TABLE, [
                    "id"                => [
                        "INT",
                        "NOT NULL",
                        "AUTO_INCREMENT",
                        "PRIMARY KEY"
                    ],
                    "identifier"    => [
                        "LONGTEXT",
                        "NOT NULL"
                    ],
                    "source_hash_key"   => [
                        "VARCHAR(40)"
                    ],
                    "platform"          => [
                        "ENUM ('yandex', 'topdealers', 'google', 'zoon', 'yell', 'twogis')",
                        "NOT NULL"
                    ],
                    "text"              => [
                        "LONGTEXT"
                    ],
                    "rating"            => [
                        "TINYINT(2)",
                        "NOT NULL"
                    ],
                    "tonal"             => [
                        "ENUM ('NEGATIVE', 'NEUTRAL', 'POSITIVE')"
                    ],
                    "date"              => [
                        "BIGINT",
                        "NOT NULL"
                    ],
                ]);
                break;

            case self::TASK_QUEUE_TABLE:
                $this->database->create(self::TASK_QUEUE_TABLE, [
                    "source_hash_key"   => [
                        "VARCHAR(40)",
                        "NOT NULL",
                    ],
                    "last_parse_date"   => [
                        "INT",
                        "NOT NULL"
                    ],
                    "review_per_day"    => [
                        "TINYINT",
                        "NOT NULL"
                    ]
                ]);
                break;

            default:
                throw new Exception('В перечне таблиц присутствует имя, для которой не написано миграции');
        }
    }

    private function setForeignKey() {
        $this->database->query("
                        ALTER TABLE review 
                        ADD FOREIGN KEY (source_hash_key) 
                        REFERENCES source_review(source_hash)
                        ON DELETE SET NULL 
        ");

        $this->database->query("
                        ALTER TABLE task_queue
                        ADD FOREIGN KEY (source_hash_key)
                        REFERENCES source_review(source_hash)
        ");
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
;