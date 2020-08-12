<?php
// todo: Написать функцию получает необработанные источники из БД по текущей платформе, и убрать заглушку
// todo: Отправка сообщения об отзывах, с необходимой тональностью вне модуля

namespace parsing;

use parsing\factories\ParserFactory;

class ParsingController
{
    private $platform;
    private $sources;

    public function __construct($platform)
    {
        $this->platform = $platform;
        $this->sources = $this->getActualSources();
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

    public function getActualSources() {
        return (new DbController())->getActualSourceReview();
    }

    public function sendMessage(){
        return 'sended';
    }
}