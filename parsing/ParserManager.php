<?php
namespace parsing;

use Exception;
use parsing\factories\ParserFactory;
use parsing\DB\DatabaseShell;

class ParserManager
{
    private $sources;

    public function __construct() {
        $this->sources = $this->getActualSources();
    }

    public function parseSources() {
        foreach ($this->sources as $source){
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
        }
    }

    private function getActualSources() {
//        return (new DatabaseShell())->getActualSourceReviews();
        return [
            [
                'source'=>'https://topdealers.ru/brands/kia/moskva/2406/',
                'handle'=>'NEW',
                'platform'=>'topdealers',
                'source_hash'=>'dsfsfs',
                'config'=>[]
            ]

               ];
    }
}