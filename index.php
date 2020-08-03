<?php

use parsing\platforms\zoon\ZoonGetter;
use parsing\platforms\zoon\ZoonFilter;


require_once "autoloader.php";
require_once "vendor/autoload.php";


ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// todo: Изучить тему, связанную с использованием прокси, и избеганием бана от площадок.


$getter = new ZoonGetter();
$filter = new ZoonFilter();


$getter->setSource('https://volgograd.zoon.ru/medical/psihoterapevticheskij_kabinet_blagovest_i_ko');
$getter->setActual('actual');
$getter->setTrack('track');

/*
$doc = phpQuery::newDocument($buffer);
$temp1 = $doc->find('.js-comment-short-text.comment-text')->text(););


var_dump($temp2);*/

$buffer = $getter->getNextReviews();
$buffer = $getter->getNextReviews();
$buffer = $filter->clearData($buffer);
//var_dump($buffer);