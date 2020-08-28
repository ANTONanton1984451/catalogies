<?php

namespace parsing;

use Exception;
use parsing\factories\ParserFactory;
use parsing\DB\DatabaseShell;

class ParserManager {

    const NEW_WORKER = 0;
    const HIGH_PRIORITY_WORKER = 1;
    const LOW_PRIORITY_WORKER = 2;

    const HIGH_PRIORITY_PLATFORMS = [
        "'google'",
        "'zoon'"
    ];

    const LOW_PRIORITY_PLATFORMS = [
        "'topdealers'",
        "'yell'"
    ];

    const SOURCES_LIMIT = 2;

    private $worker;
    private $sources = [];

    public function __construct($worker) {
        $this->worker = $worker;
        $this->sources = $this->getActualSources($worker);
    }

    public function parseSources() {
        if (count($this->sources) == 0) {
            echo "Worker #$this->worker: empty_sources \n";
            return "empty_sources";
        }

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

        echo "Worker #$this->worker: Success parsing \n";
        return 'success';
    }
    private function getActualSources($worker) {
        switch ($worker) {
            case self::NEW_WORKER:
                return (new DatabaseShell())
                    ->getSources(self::SOURCES_LIMIT, "NEW");


            case self::HIGH_PRIORITY_WORKER:
                return (new DatabaseShell())
                    ->getSources(self::SOURCES_LIMIT, "HANDLED", self::HIGH_PRIORITY_PLATFORMS);

            case self::LOW_PRIORITY_WORKER:
                return (new DatabaseShell())
                    ->getSources(self::SOURCES_LIMIT, "HANDLED", self::LOW_PRIORITY_PLATFORMS);

            default:
                throw new Exception('Unknown worker');
        }

    }
}