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
            ['source'=>'accounts/101148201288830043360/locations/5839617167530752762',
            'handle'=>'HANDLED',
            'config'=>['token_info'=>[
                                      'access_token'=>'ya29.a0AfH6SMCqK_uYgcKs_m3BYtxJkZXY14xF17ZgMna9Wehs71T1B-QFz9rIQ09ZKCrQhVbXLivIWV_pWkRpozL4aSSNSJL6joY6U_fUKJXw-zemBO2GqBZNoAhDlqDCiMmablo3L7HDnySebUcqwy6Xg77qHVzT2tlkymE',
                                      'expires_in'=>3599,
                                      'refresh_token'=>'1//0cwRlcDo1bYS8CgYIARAAGAwSNwF-L9Irn6M6HHnO2n2GpNxJcP9feTd22DsOctFe5Nk-SN0lx9zC1K1945mWCuep7YMNOtBhZ74',
                                      'scope'=>'https://www.googleapis.com/auth/business.manage',
                                      'token_type'=>'Bearer',
                                      'created'=>'1596620606'
                                     ],
                        'last_review_date'=>1596527426,
                        'last_review_hash'=>'d3164491'
                      ]
            ]

        ];
    }

    public function sendMessage(){
        // todo: Отправка сообщения об отзывах, с необходимой тональностью вне модуля
        return 'sended';
    }
}