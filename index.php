<?php


use parsing\ParserManager;
use parsing\platforms\zoon\ZoonGetter;
use parsing\platforms\zoon\ZoonFilter2;
use parsing\platforms\zoon\ZoonModel;
use parsing\logger\LoggerManager;


setSource(['https://volgograd.zoon.ru/restaurants/bar_vedrov/']);
loopGo();

function setSource(array $sources)
{
    foreach ($sources as $source) {
        $db = new DatabaseShell();
        $db->insertSourceReview([
            'source_hash' => md5($source),
            'platform' => 'zoon',
            'source' => $source,
            'actual' => 'ACTIVE',
            'track' => 'ALL',
            'handled' => 'NEW'
        ]);
    }
}

function loopGo() {
    $worker = new Worker();
    $worker->count = 1;

    $worker->onWorkerStart = function ($worker) {
        $timeInterval = 50;
        $timerId = Timer::add($timeInterval, function () {
            (new ParserManager())->parseSources();
        });
    };

    Worker::runAll();
}

