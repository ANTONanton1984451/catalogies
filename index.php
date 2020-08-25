<?php

require_once "vendor/autoload.php";
require_once "autoloader.php";

use parsing\DB\DatabaseShell;
use Workerman\Worker;
use Workerman\Timer;
use parsing\ParserManager;

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

loopGo();

//setSource(['https://volgograd.zoon.ru/restaurants/bar_vedrov/']);

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
//    $worker = new Worker();
//    $worker->count = 1;
//    $worker->name = "Worker for catalogs module";
//    $worker->onWorkerStart = (function (){
//        $timeInterval = 20;
//        $timerId = Timer::add($timeInterval, function () {

        $manager = new ParserManager();
        $manager->parseSources();
//
//        });
//    });
//
//    Worker::runAll();

}