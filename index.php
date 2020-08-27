<?php
require_once "autoloader.php";
require_once "vendor\autoload.php";

use parsing\logger\LoggerManager;

LoggerManager::init();

(new \parsing\ParserManager())->parseSources();



