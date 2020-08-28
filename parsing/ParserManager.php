<?php

namespace parsing;

use Exception;
use parsing\factories\ParserFactory;
use parsing\DB\DatabaseShell;

class ParserManager
{
//    const NEW_WORKER = 0;
//    const HIGH_PRIORITY_WORKER = 1;
//    const LOW_PRIORITY_WORKER = 2;

    private $worker;
    private $sources;

    public function __construct($worker)
    {
        $this->sources = $this->getActualSources($worker);
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
        }

        return 'success';
    }
    private function getActualSources($worker)
    {


//        return (new DatabaseShell())->getActualSources(1, ["'yell'"]);
        return (new DatabaseShell())->getNewSources(1);


    }
}