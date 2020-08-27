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
        return (new DatabaseShell())->getNewSources(1);
//        return (new DatabaseShell())->getActualSourceReviews();
    }
}