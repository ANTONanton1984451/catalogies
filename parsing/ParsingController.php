<?php
// todo: Написать функцию получает необработанные источники из БД по текущей платформе, и убрать заглушку
// todo: Отправка сообщения об отзывах, с необходимой тональностью вне модуля

namespace parsing;

use parsing\factories\ParserFactory;

class ParsingController
{
    private $platform;
    private $sources;

    public function __construct($platform, $bd)
    {
        $this->platform = $platform;
        $this->sources = $this->getActualSources($bd);
    }

    public function parsePlatform() {
        $parser_factory = (new ParserFactory())->getFactory($this->platform);

        foreach ($this->sources as $source){
            $parser = new Parser($source);
            $parser->setGetter($parser_factory->buildGetter());
            $parser->setFilter($parser_factory->buildFilter());
            $parser->setModel($parser_factory->buildModel());
            $parser->parseSource();
        }
    }

    public function getActualSources($bd) {
        return
        [
            [
                'source'    => 'https://volgograd.zoon.ru/beauty/salon_krasoty_style_na_ulitse_karla_marksa/',
                'handled'   => 'handled',
                'track'     => 'track',
                'config'    => ['token_info'=>  [
                                                'access_token'=>'ya29.a0AfH6SMBm0qdJzUEVv--ZLVzhyI_yThxf_mmZLFAYKD1objFTLqw66suupUg_GhozaDdLKxCR-liGLGonA-z9WjFvXS6NjiRpJ36559-M3tGWbd6EgDODJtV8Ro_e-q9L1Tmj4Np7ecnbAP3hw4Jck8qZWKJ7GuJZmb0',
                                                'expires_in'=>3599,
                                                'refresh_token'=>'1//0cd1Cb9-Zg1qzCgYIARAAGAwSNwF-L9IrJux96rezRKOQ3JcuuQ-hccinCBHbOsOlvi9M7PCdqMND8_EJgsFLobMLKdTYm_VDBmE',
                                                'scope'=>'https://www.googleapis.com/auth/business.manage',
                                                'token_type'=>'Bearer',
                                                'created'=>'1596620606'
                                            ],
                        'last_review_date'  =>  1596527429,
                        'last_review_hash'  =>  'd3164491'
                      ]
            ]

        ];
    }

    public function sendMessage(){
        // todo: Отправка сообщения об отзывах, с необходимой тональностью вне модуля
        return 'sended';
    }
}