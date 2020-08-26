<?php

require_once "vendor/autoload.php";
require_once "autoloader.php";

use parsing\DB\DatabaseShell;
use parsing\ParserManager;
use Workerman\Worker;
use Workerman\Timer;


setSource(['https://volgograd.zoon.ru/restaurants/kapuchino_v_krasnooktyabrskom_rajone/']);
//loopGo();
(new ParserManager())->parseSources();

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

function loopGo()
{
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
