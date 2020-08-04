<?php

use parsing\ParsingController;
use parsing\platforms\zoon\ZoonGetter;
use parsing\platforms\zoon\ZoonFilter;
use parsing\PController;

require_once "autoloader.php";
require_once "vendor/autoload.php";

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$controller = new PController('zoon', 'database');
$controller->getActualSources('database');
$controller->parsePlatform();

$controller->sendMessage();



/*
$controller = new ParsingController('zoon', 'database');
$controller->getActualSources('database');
$controller->parsePlatform();
$controller->sendMessage();


$getter = new ZoonGetter();
$filter = new ZoonFilter();

$getter->setSource('https://volgograd.zoon.ru/restaurants/kitajskij_restoran_zolotoj_drakon_v_dzerzhinskom_rajone/');
$getter->setHandled('handled');
$getter->setTrack('track');

$buffer = $getter->getNextReviews();
$buffer = $filter->clearData($buffer);
var_dump($buffer);
*/