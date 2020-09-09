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
                        "ENUM('yandex', 'topdealers', 'google', 'zoon', 'yell', 'twogis', 'flamp')",
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
                        "ENUM('NEW', 'HANDLED', 'NON_COMPLETED', 'NON_UPDATED', 'UNPROCESSABLE')",
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
                        "ENUM ('yandex', 'topdealers', 'google', 'zoon', 'yell', 'twogis', 'flamp')",
                        "NOT NULL"
                    ],
                    "text" => [
                        "LONGTEXT"
                    ],
                    "is_answered" => [
                        "BOOLEAN",
                        "NOT NULL"
                    ],
                    "answer" => [
                        "LONGTEXT"
                    ],
                    "rating" => [
                        "TINYINT(2)",
                        "NOT NULL"
                    ],
                    "tonal" => [
                        "ENUM ('NEGATIVE', 'NEUTRAL', 'POSITIVE', 'NONE')"
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
                        ON UPDATE CASCADE 
        ");

        $this->database->query("
                        ALTER TABLE task_queue
                        ADD FOREIGN KEY (source_hash_key)
                        REFERENCES source_review(source_hash)
        ");
    }


    public function seedDatabase() {
        $platforms = [
//            'flamp',
//            'topdealers',
            'yell',
//            'zoon',
        ];

        $flamp_links = [
            'https://krasnodar.flamp.ru/firm/il_patio_set_restoranov-3237490513310210',
            'https://krasnodar.flamp.ru/firm/amsterdambar_bar-3237490513262584',
            'https://spb.flamp.ru/firm/smoke_bbq_barbekyu_bar_ooo_pech-70000001025941327',
            'https://spb.flamp.ru/firm/kozhno_venerologicheskijj_dispanser_5-5348552838587562',
            'https://spb.flamp.ru/firm/spot_set_stancijj_po_zamene_masla-70000001022228050',
        ];

        $topdealers_links = [
            'https://topdealers.ru/brands/renault/belgorod/1425/',
            'https://topdealers.ru/brands/nissan/sankt-peterburg/5009/',
            'https://topdealers.ru/brands/audi/moskva/69/'
        ];

        $yell_links = [
            'https://www.yell.ru/moscow/com/lawson-s-bar_12035706/',
            'https://www.yell.ru/moscow/com/karaoke-bar-fortepyano_11919795/',
            'https://www.yell.ru/moscow/com/tancevalnyj-klub-miks-afterparty_11846202/',
            'https://www.yell.ru/moscow/com/look-in-rooms_9763298/',
        ];

        $zoon_links = [
            'https://volgograd.zoon.ru/restaurants/kapuchino_v_krasnooktyabrskom_rajone/',
            'https://volgograd.zoon.ru/restaurants/kafe_3_sushi_na_ulitse_karbysheva/',
            'https://volgograd.zoon.ru/restaurants/restoran-bar_velvet/',
            'https://volgograd.zoon.ru/restaurants/restoran_bar_grill_na_ulitse_mira/',
            'https://volgograd.zoon.ru/restaurants/restoran_hampton_by_hilton/',
            'https://volgograd.zoon.ru/restaurants/shashlyk-bar_piratskaya_pristan_na_ulitse_marshala_rokossovskogo/',
            'https://volgograd.zoon.ru/beauty/kosmetologicheskaya_klinika_soprano/',
        ];

        foreach ($platforms as $platform) {


            $sources = $platform . "_links";
            foreach ($$sources as $source) {
                $db = new DatabaseShell();
                $db->insertSourceReview([
                    'source_hash' => md5($source),
                    'platform' => $platform,
                    'source' => $source,
                    'actual' => 'ACTIVE',
                    'track' => 'ALL',
                    'handled' => 'NEW'
                ]);
            }
        }


        $google_links = [
            [
                'source'=>'accounts/101148201288830043360/locations/5839617167530752762',
                'config'=>[
                    'token_info'=>[
                          'access_token' =>  'ya29.a0AfH6SMClm14SrVXboygJiAhw9IckyEg5pYsCE64YMLsq30RbxQSoJtUcTHtI9GsiTQD6rCAWjwoXQdJ1E-vZ8GNAi5IhVXCHGQn14xQpcFhYbeeribO4GZVctAp9p7YwZEZugc1zftYmUB9gzGAojDIwKJspZbFZquc',
                          'expires_in' =>  3599,
                          'refresh_token' => '1//0civO_apGzWFeCgYIARAAGAwSNwF-L9Ir01TiziSGGG33fFDsSFaPwffgvsjwetQhqAYpwjmKMjJs-RPuxOR9UwP9PU61nfZmDvc',
                          'scope' =>  'https://www.googleapis.com/auth/business.manage',
                          'token_type' => 'Bearer',
                          'created' => 1598615609
                    ]
                ]
            ],
            [
                'source'=>'accounts/101148201288830043360/locations/2321278413977180698',
                'config'=>[
                    'token_info'=>[
                        'access_token' =>  'ya29.a0AfH6SMClm14SrVXboygJiAhw9IckyEg5pYsCE64YMLsq30RbxQSoJtUcTHtI9GsiTQD6rCAWjwoXQdJ1E-vZ8GNAi5IhVXCHGQn14xQpcFhYbeeribO4GZVctAp9p7YwZEZugc1zftYmUB9gzGAojDIwKJspZbFZquc',
                        'expires_in' =>  3599,
                        'refresh_token' => '1//0civO_apGzWFeCgYIARAAGAwSNwF-L9Ir01TiziSGGG33fFDsSFaPwffgvsjwetQhqAYpwjmKMjJs-RPuxOR9UwP9PU61nfZmDvc',
                        'scope' =>  'https://www.googleapis.com/auth/business.manage',
                        'token_type' => 'Bearer',
                        'created' => 1598615609
                    ]
                ]
            ]
        ];

        foreach ($google_links as $source){
            $db = new DatabaseShell();
            $db->insertSourceReview([
                'source_hash' => md5($source['source']),
                'platform' => 'google',
                'source' => $source['source'],
                'actual' => 'ACTIVE',
                'track' => 'ALL',
                'handled' => 'NEW',
                'source_config'=>json_encode($source['config'])
            ]);
        }
    }



    private function getConnection():Medoo
    {
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
