<?php

require_once "vendor/autoload.php";
require_once "autoloader.php";

use parsing\DB\DatabaseShell;
use parsing\ParserManager;
use Workerman\Worker;
use Workerman\Timer;

define("NEW_WORKER", 0);
define("HIGH_PRIORITY_WORKER", 1);
define("LOW_PRIORITY_WORKER", 2);

setSource(['https://volgograd.zoon.ru/restaurants/kapuchino_v_krasnooktyabrskom_rajone/']);
//loopGo();

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
    $newSourcesWorker = new Worker();
    $newSourcesWorker->name = "NEW sources worker";
    $newSourcesWorker->count = 1;

    $newSourcesWorker->onWorkerStart = function ($newSourcesWorker) {
        $timeInterval = 20;
        $timerId = Timer::add($timeInterval, function () {
            (new ParserManager(NEW_WORKER))->parseSources();
        });
    };

    $highPriorityWorker = new Worker();
    $highPriorityWorker->name = "HIGH PRIORITY sources worker";
    $highPriorityWorker->count = 1;

    $highPriorityWorker->onWorkerStart = function ($highPriorityWorker) {
        $timeInterval = 20;
        $timerId = Timer::add($timeInterval, function () {
            (new ParserManager(HIGH_PRIORITY_WORKER))->parseSources();
        });
    };

    $lowPriorityWorker = new Worker();
    $lowPriorityWorker->name = "LOW PRIORITY sources worker";
    $lowPriorityWorker->count = 180;

    $lowPriorityWorker->onWorkerStart = function ($lowPriorityWorker) {
        $timeInterval = 20;
        $timerId = Timer::add($timeInterval, function () {
            (new ParserManager(LOW_PRIORITY_WORKER))->parseSources();
        });
    };

    Worker::runAll();
}



