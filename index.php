<?php

use parsing\ParserManager;
use parsing\platforms\zoon\ZoonGetter;
use parsing\platforms\zoon\ZoonFilter2;
use parsing\platforms\zoon\ZoonModel;
use parsing\DB\DatabaseShell;

require_once "autoloader.php";
require_once "vendor/autoload.php";

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

//$manager = new ParserManager();
//$manager->parseSources();

//setSource();

$config = (new DatabaseShell())->getActualSourceReviews();
$config = $config[0];

$getter = new ZoonGetter();
$filter = new ZoonFilter2();
$model = new ZoonModel();

$getter->setConfig($config);
$filter->setConfig($config);
$model->setConfig($config);

$buffer = $getter->getNextRecords();
$buffer = $getter->getNextRecords();
$buffer = $getter->getNextRecords();

$buffer = $filter->clearData($buffer);
//$model->writeData($buffer);

function setSource() {
    $source = 'https://volgograd.zoon.ru/restaurants/kapuchino_v_krasnooktyabrskom_rajone/';
    $db = new \parsing\DB\DatabaseShell();
    $db->insertSourceReview([
        'source_hash'   =>  md5($source),
        'platform'      =>  'zoon',
        'source'        =>  $source,
        'actual'        =>  'ACTIVE',
        'track'         =>  'ALL',
        'handled'       =>  'NEW'
    ]);
}