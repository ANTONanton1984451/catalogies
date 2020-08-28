<?php

require_once "vendor/autoload.php";
require_once "autoloader.php";

use parsing\ParserManager;
use Workerman\Worker;
use Workerman\Timer;

define("NEW_WORKER", 0);
define("HIGH_PRIORITY_WORKER", 1);
define("LOW_PRIORITY_WORKER", 2);


loopGo();


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
    $lowPriorityWorker->count = 1;

    $lowPriorityWorker->onWorkerStart = function ($lowPriorityWorker) {
        $timeInterval = 20;
        $timerId = Timer::add($timeInterval, function () {
            (new ParserManager(LOW_PRIORITY_WORKER))->parseSources();
        });
    };

    Worker::runAll();
}



