<?php

namespace parsing;

use Pimple\Container;

class ParsingController
{
    private $platform;

    /**
     * Массив с ссылками, парсинг которых происходит
     * @var array
     */
    private $sources;

    private $container = new Container();

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