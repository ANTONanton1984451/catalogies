<?php

namespace parsing;

class PController
{
    private $platform;
    private $sources;

    public function __construct($platform, $bd)
    {
        $this->platform = $platform;
        $this->sources = $this->getActualSources($bd);
    }

    public function parsePlatform() {
        foreach ($this->sources as $source){
            $parser = new P($this->platform, $source);
            $parser->parseSource();
        }
    }

    public function getActualSources($bd) {
        return
        [
            'https://volgograd.zoon.ru/beauty/salon_krasoty_style_na_ulitse_karla_marksa/',
            'https://volgograd.zoon.ru/restaurants/kafe_blin_klub_na_sovetskoj_ulitse/',
            'https://volgograd.zoon.ru/restaurants/kitajskij_restoran_zolotoj_drakon_v_dzerzhinskom_rajone/',
            'https://volgograd.zoon.ru/shops/kulinariya_konfetki-baranochki_na_prospekte_lenina-9fcd/',
            'https://volgograd.zoon.ru/autoservice/atts_plaza/',
        ];
    }

    public function sendMessage(){
        // todo: Отправка сообщения об отзывах, с необходимой тональностью вне модуля
        return 'sended';
    }
}