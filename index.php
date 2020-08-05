<?php

use parsing\ParsingController;
use parsing\platforms\zoon\ZoonGetter;
use parsing\platforms\zoon\ZoonFilter;

require_once "autoloader.php";

require_once "vendor/autoload.php";


ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$controller = new ParsingController('google', 'database');
$controller->getActualSources('database');
$controller->parsePlatform();

//$controller->sendMessage();


