<?php

namespace parsing\DB;

use Exception;
use Medoo\Medoo;
use PDO;

class MigrationsManager
{
    private $database;

    const TASK_QUEUE_TABLE = 'task_queue';
    const REVIEW_TABLE = 'review';
    const SOURCE_REVIEW = 'source_review';

    const SCHEMAS = [
        self::TASK_QUEUE_TABLE,
        self::REVIEW_TABLE,
        self::SOURCE_REVIEW,
    ];

    public function __construct()
    {
        $this->database = $this->getConnection();
    }

    public function createSchema()
    {
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

    public function dropTables()
    {
        foreach (self::SCHEMAS as $item) {
            $this->database->drop($item);
        }
    }

    private function createTable($name)
    {

        switch ($name) {
            case self::SOURCE_REVIEW:
                $this->database->create(self::SOURCE_REVIEW, [
                    "source_hash" => [
                        "VARCHAR(40)",
                        "NOT NULL",
                        "PRIMARY KEY"
                    ],
                    "platform" => [
                        "ENUM('yandex', 'topdealers', 'google', 'zoon', 'yell', 'twogis')",
                        "NOT NULL"
                    ],
                    "source" => [
                        "LONGTEXT",
                        "NOT NULL"
                    ],
                    "source_meta_info" => [
                        "LONGTEXT"
                    ],
                    "source_config" => [
                        "LONGTEXT"
                    ],
                    "actual" => [
                        "ENUM('UNACTIVE', 'ACTIVE')",
                        "NOT NULL"
                    ],
                    "track" => [
                        "ENUM('ALL', 'NEGATIVE', 'NONE')",
                        "NOT NULL"
                    ],
                    "handled" => [
                        "ENUM('NEW', 'HANDLED')",
                        "NOT NULL"
                    ]
                ]);
                break;

            case self::REVIEW_TABLE:
                $this->database->create(self::REVIEW_TABLE, [
                    "id" => [
                        "INT",
                        "NOT NULL",
                        "AUTO_INCREMENT",
                        "PRIMARY KEY"
                    ],
                    "identifier" => [
                        "LONGTEXT",
                        "NOT NULL"
                    ],
                    "source_hash_key" => [
                        "VARCHAR(40)"
                    ],
                    "platform" => [
                        "ENUM ('yandex', 'topdealers', 'google', 'zoon', 'yell', 'twogis')",
                        "NOT NULL"
                    ],
                    "text" => [
                        "LONGTEXT"
                    ],
                    "rating" => [
                        "TINYINT(2)",
                        "NOT NULL"
                    ],
                    "tonal" => [
                        "ENUM ('NEGATIVE', 'NEUTRAL', 'POSITIVE')"
                    ],
                    "date" => [
                        "BIGINT",
                        "NOT NULL"
                    ],
                ]);
                break;

            case self::TASK_QUEUE_TABLE:
                $this->database->create(self::TASK_QUEUE_TABLE, [
                    "source_hash_key" => [
                        "VARCHAR(40)",
                        "NOT NULL",
                    ],
                    "last_parse_date" => [
                        "INT",
                        "NOT NULL"
                    ],
                    "review_per_day" => [
                        "TINYINT",
                        "NOT NULL"
                    ]
                ]);
                break;

            default:
                throw new Exception('В перечне таблиц присутствует имя, для которой не написано миграции');
        }
    }

    private function setForeignKey()
    {
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

    public function seedDatabase() {
        $links = [
            'https://volgograd.zoon.ru/restaurants/restoran-bar_velvet/',
            'https://volgograd.zoon.ru/restaurants/kapuchino_v_krasnooktyabrskom_rajone/',
            'https://volgograd.zoon.ru/beauty/salon_krasoty_style_na_ulitse_karla_marksa/',
            'https://volgograd.zoon.ru/trainings/detskij_klub_akademiya_geniev_na_ulitse_mira/',
            'https://www.yell.ru/spb/com/rossiya-sankt-peterburg-ulitsakolomenskaya29-restoran-philibert-nakolomenskojulitse_9765413/',
            'https://www.yell.ru/spb/com/restoran-baku-na-sadovoj-ulice_11958638/',
            'https://www.yell.ru/spb/com/antikafe-poltavskaya-7-na-metro-ploshchad-vosstaniya_11886901/',
            'https://topdealers.ru/brands/bmw/moskva/2201/',
            'https://topdealers.ru/brands/renault/belgorod/1425/',
        ];

        $googleSourceReview = [
            'source' => 'accounts/101148201288830043360/locations/5839617167530752762',
            'source_config' =>  json_encode([
                'access_token' => 'ya29.a0AfH6SMC2vIRifpmGxmqj7IfphUhAPG8i9KMWGnr04TZ5RucPNWTLuUNy-qRJ1fpjpVTNTZCyHuJCaWTv_m9G78Fn_gJO2VeOToSYbvz2QkMcjl__0YrMS74vXQmzx2gAX0zIrAc-qx2TQCMgpUI_P89kj0p1OurhH40',
                'expires_in' => 3599,
                'refresh_token' => '1//0cg7RCGjabTR2CgYIARAAGAwSNwF-L9IrGGe5SojDMLp6RXZ8HYHdbY1m3pvKKfBGNqpKVihjX9VhSTxNv7FpdGfhBrpedAZsVdI',
                'scope' => 'https://www.googleapis.com/auth/business.manage',
                'token_type' => 'Bearer',
                'created' => 1598335250
            ])
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