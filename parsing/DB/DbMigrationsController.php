<?php
// todo: refactor, однозначно

use Medoo\Medoo;

class DbMigrationsController {
    private $database;

    const TASK_QUEUE_TABLE          = 0;
    const ADD_INFO_REVIEW_TABLE     = 1;
    const REVIEW_TABLE              = 2;
    const SOURCE_REVIEW             = 3;

    const SCHEMAS = [
        self::TASK_QUEUE_TABLE          =>  'task_queue',
        self::ADD_INFO_REVIEW_TABLE     =>  'add_info_review',
        self::REVIEW_TABLE              =>  'review',
        self::SOURCE_REVIEW             =>  'source_review',
    ];

    public function __construct() {
        $this->database = $this->getConnection();
    }

    public function createTables() {
        foreach (self::SCHEMAS as $item) {
            $this->createTable($item);
        }
        $this->setForeignKey();
    }

    public function dropTables() {
        foreach (self::SCHEMAS as $item) {
            $this->database->drop($item);
        }
    }

    public function seedDB() {
        for ($i = 0; $i < 10000; $i++) {
            $this->database->insert(self::SCHEMAS[self::SOURCE_REVIEW], [
                'source_hash' => md5($i),
                'platform' => 'yell',
                'source' => $i,
                'source_meta_info' => 'meta' . $i,
                'source_config' => 'config' . $i,
                'actual' => 'ACTIVE',
                'track' => 'ALL',
                'handled' => 'HANDLED'
            ]);
        }

        for ($i = 0; $i < 100; $i++) {
            $this->database->insert(self::SCHEMAS[self::TASK_QUEUE_TABLE], [
                'source_hash_key'       =>  md5($i),
                'status'                =>  'WAIT',
                'action'                =>  'NOTHING'
            ]);
        }

        for ($i = 0; $i < 10000; $i++) {
            $this->database->insert(self::SCHEMAS[self::REVIEW_TABLE], [
                'source_hash_key'           =>  md5($i),
                'platform'                  =>  'yell',
                'text'                      =>  'commentary #' . $i,
                'rating'                    =>  ($i % 10),
                'tonal'                     =>  'NEUTRAL',
                'date'                      =>  $i * 2,
            ]);
        }

        for ($i = 1; $i < 1000; $i++) {
            $this->database->insert(self::SCHEMAS[self::ADD_INFO_REVIEW_TABLE], [
                'review_id'                 =>  $i,
                'identifier'                =>  'identifier #' . $i,
            ]);
        }
    }

    private function createTable($name) {
        switch ($name) {

            case self::SCHEMAS[self::SOURCE_REVIEW]:
                $this->database->create(self::SCHEMAS[self::SOURCE_REVIEW],[
                    "source_hash"           =>  [
                        "VARCHAR(40)",
                        "NOT NULL",
                        "PRIMARY KEY"
                    ],
                    "platform"              =>  [
                        "ENUM('yandex', 'topdealers', 'google', 'zoon', 'yell', 'twogis')",
                        "NOT NULL"
                    ],
                    "source"                =>  [
                        "LONGTEXT",
                        "NOT NULL"
                    ],
                    "source_meta_info"      =>  [
                        "LONGTEXT"
                    ],
                    "source_config"         =>  [
                        "LONGTEXT"
                    ],
                    "actual"                =>  [
                        "ENUM('UNACTIVE', 'ACTIVE')",
                        "NOT NULL"
                    ],
                    "track"                 =>  [
                        "ENUM('ALL', 'NEGATIVE', 'NONE')",
                        "NOT NULL"
                    ],
                    "handled"               =>  [
                        "ENUM('NEW', 'HANDLED')",
                        "NOT NULL"
                    ],
                ]);
                break;

            case self::SCHEMAS[self::REVIEW_TABLE]:
                $this->database->create(self::SCHEMAS[self::REVIEW_TABLE], [
                    "id"                => [
                        "INT",
                        "NOT NULL",
                        "AUTO_INCREMENT",
                        "PRIMARY KEY"
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

            case self::SCHEMAS[self::ADD_INFO_REVIEW_TABLE]:
                $this->database->create(self::SCHEMAS[self::ADD_INFO_REVIEW_TABLE], [
                    "review_id"     => [
                        "INT",
                        "NOT NULL",
                        "PRIMARY KEY",
                        "UNIQUE"
                    ],
                    "identifier"    => [
                        "LONGTEXT",
                        "NOT NULL"
                    ],
                ]);
                break;

            case self::SCHEMAS[self::TASK_QUEUE_TABLE]:
                $this->database->create(self::SCHEMAS[self::TASK_QUEUE_TABLE], [
                    "id"         => [
                        "INT",
                        "NOT NULL",
                        "AUTO_INCREMENT",
                        "PRIMARY KEY"
                    ],
                    "source_hash_key"   => [
                        "VARCHAR(40)",
                        "NOT NULL",
                    ],
                    "status"            => [
                        "ENUM('WAIT', 'COMPLETE')",
                        "NOT NULL"
                    ],
                    "action"            => [
                        "ENUM('NOTHING')"
                    ]
                ]);
                break;
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
                        ALTER TABLE add_info_review
                        ADD FOREIGN KEY (review_id)
                        REFERENCES review(id)
                        ON DELETE CASCADE 
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
            'username'          => 'borland',
            'password'          => 'attache1974',
            'option'            => [
                PDO::ATTR_ERRMODE   =>  PDO::ERRMODE_EXCEPTION
            ]
        ]);
    }
}
;