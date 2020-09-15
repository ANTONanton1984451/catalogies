<?php

namespace parsing;

use Exception;
use parsing\factories\factory_interfaces\ConstantInterfaces;
use parsing\factories\ParserFactory;
use parsing\DB\DatabaseShell;

class ParserManager implements ConstantInterfaces {
    const SOURCES_LIMIT = 3;

    private $worker;
    private $sources = [];
    private $notifications;
    private $dataBaseShell;

    public function __construct($workerType,DatabaseShell $dataBaseShell) {
        $this->worker = $workerType;
        $this->dataBaseShell = $dataBaseShell;
        $this->sources = $this->getActualSources($workerType);

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
        $notificationsForSend = [];
        foreach ($this->notifications as $v){
            if($v['container']['type'] !== self::TYPE_EMPTY){
                $notificationsForSend[] = $v;
            }
        }
        var_dump($notificationsForSend);
        json_encode($notificationsForSend);
    }

    private function getActualSources($workerType) {
        switch ($workerType) {
            case NEW_WORKER:
                return $this->dataBaseShell
                    ->getSources(self::SOURCES_LIMIT, self::SOURCE_NEW);

            case HIGH_PRIORITY_WORKER:
                return $this->dataBaseShell
                    ->getSources(self::SOURCES_LIMIT, self::SOURCE_HANDLED, HIGH_PRIORITY_PLATFORMS);

            case LOW_PRIORITY_WORKER:
                return $this->dataBaseShell
                    ->getSources(self::SOURCES_LIMIT, self::SOURCE_HANDLED, LOW_PRIORITY_PLATFORMS);

            case NON_COMPLETED_WORKER:
                return $this->dataBaseShell
                    ->getSources(self::SOURCES_LIMIT, self::SOURCE_NON_COMPLETED);

            case NON_UPDATED_WORKER:
                return $this->dataBaseShell
                    ->getSources(self::SOURCES_LIMIT, self::SOURCE_NON_UPDATED);

            default:
                throw new Exception('Unknown worker');
        }

    }
}