<?php

use parsing\ParsingController;

require_once "autoloader.php";
require_once "vendor/autoload.php";

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$controller = new ParsingController('zoon');
$controller->getActualSources();
$controller->parsePlatform();

//$controller->sendMessage();