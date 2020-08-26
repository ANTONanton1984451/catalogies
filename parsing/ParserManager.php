<?php

namespace parsing;

use Exception;
use parsing\factories\ParserFactory;
use parsing\DB\DatabaseShell;

class ParserManager
{
    private $sources;

    public function __construct()
    {
        $this->sources = $this->getActualSources();
    }

    public function parseSources()
    {
        foreach ($this->sources as $source) {
            try {
                $parser_factory = (new ParserFactory())->getFactory($source['platform']);
            } catch (Exception $e) {
                continue;
            }

            $parser = new Parser($source);
            $parser->setGetter($parser_factory->buildGetter());
            $parser->setFilter($parser_factory->buildFilter());
            $parser->setModel($parser_factory->buildModel());
            $parser->parseSource();
            sleep(40);
        }

        return 'success';
    }
    private function getActualSources()
    {
        return [

            ['source'=>'accounts/101148201288830043360/locations/5839617167530752762',
            'handled'=>'NEW',
            'source_hash'=>'test',
            'platform'=>'google',
            'config'=>['token_info'=>['access_token'=>'ya29.a0AfH6SMDfTmL5TIt49uhenwd4G7iBHuB5P0ZmzeXL4pNzu7JTYnjrZ-QgAW52-0dn8yFNbBAjZ4OqVg5EuJ4u5cW59dyqb25y9UmLzDTX3v2mX8TI5T4V8CNB8faWbT6-t7qPKd5BDfJSOQhylxIRoOwltUkh2yjPd94',
                                      'expires_in'=>3599,
                                      'refresh_token'=>'1//0cwA_6VvU72mvCgYIARAAGAwSNwF-L9IrZ4DHKgbeu8hNi1J6Cpixk2UN4-J4j-qm0GDdAkb964XC4BcAn1NeaZsLNAQGYdg6yvY',
                                      'scope'=>'https://www.googleapis.com/auth/business.manage',
                                      'token_type'=>'Bearer',
                                      'created'=>1598423983],
                        'last_review_hash'=>'test',
                        'last_review_date'=>1597655659
            ]
    ]
        ];
//        return (new DatabaseShell())->getActualSourceReviews();
    }
}