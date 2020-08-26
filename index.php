<?php
require_once "autoloader.php";
require_once "vendor\autoload.php";

use parsing\logger\LoggerManager;

LoggerManager::init();

//(new \parsing\ParserManager())->parseSources();

$dbShell = new \parsing\DB\DatabaseShell();

$res = $dbShell->getActualSources(1,["'google'"]);

var_dump($res);

