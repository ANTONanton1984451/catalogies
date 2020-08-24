<?php

use parsing\ParserManager;
use parsing\platforms\zoon\ZoonGetter;
use parsing\platforms\zoon\ZoonFilter2;
use parsing\platforms\zoon\ZoonModel;


require_once "autoloader.php";
require_once "vendor/autoload.php";

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);



(new ParserManager())->parseSources();

