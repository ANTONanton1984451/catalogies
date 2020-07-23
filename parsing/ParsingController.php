<?php

namespace parsing;

class ParsingController
{
    private $platform;
    private $sources = [];

    public function __construct($platform, $bd)
    {
        $this->platform = $platform;
        $this->sources = $this->getActualSources($bd);
    }

    public function parsePlatform() {
        foreach ($this->sources as $source){
            $parser = new Parser($this->platform, $source);
            $parser->//todo: реализовать метод парсинга
            $this->sendMessage($parser->generateJsonMessage());
        }
    }

    public function getActualSources($bd) {
        // todo: Написать функцию получает необработанные источники из БД по текущей платформе
        return 'sources';
    }

    public function sendMessage(){
        // todo: Отправка сообщения об отзывах, с необходимой тональностью вне модуля
        return 'sended';
    }
}