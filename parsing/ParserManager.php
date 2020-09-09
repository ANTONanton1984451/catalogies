<?php

namespace parsing;

use Exception;
use parsing\factories\factory_interfaces\ConstantInterfaces;
use parsing\factories\ParserFactory;
use parsing\DB\DatabaseShell;

class ParserManager implements ConstantInterfaces
{
    const HIGH_PRIORITY_PLATFORMS = [
        "'google'",
        "'zoon'"
    ];

    const LOW_PRIORITY_PLATFORMS = [
        "'topdealers'",
        "'yell'"
    ];
    
    const SOURCES_LIMIT = 3;

    private $worker;
    private $sources = [];
    private $notifications;

    public function __construct($worker) {
        $this->worker = $worker;
        $this->sources = $this->getActualSources($worker);
    }

    public function parseSources() {
        if (count($this->sources) == 0) {
            echo "Worker #$this->worker: not sources for parsing \n";
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
            $this->notifications[] = $parser->generateNotifications();

        }
        $this->notify();
        echo "Worker #$this->worker: Success parsing \n";
        return 'success';
    }

    /**
     * todo:пока заглушка
     * todo:нужно поставить проверку на пустой контейнер
     */
    private function notify():void
    {
        var_dump(json_encode($this->notifications));
    }

    private function getActualSources($worker) {
        switch ($worker) {
            case NEW_WORKER:
                return (new DatabaseShell())
                    ->getSources(self::SOURCES_LIMIT, "NEW");


            case HIGH_PRIORITY_WORKER:
                return (new DatabaseShell())
                    ->getSources(self::SOURCES_LIMIT, "HANDLED", self::HIGH_PRIORITY_PLATFORMS);

            case LOW_PRIORITY_WORKER:
                return (new DatabaseShell())
                    ->getSources(self::SOURCES_LIMIT, "HANDLED", self::LOW_PRIORITY_PLATFORMS);

            default:
                throw new Exception('Unknown worker');
        }

    }
}