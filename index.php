<?php

use parsing\platforms\zoon\ZoonGetter;

require_once "autoloader.php";
require_once "vendor/autoload.php";

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$zoon = new ZoonGetter('https://volgograd.zoon.ru/restaurants/kapuchino_v_krasnooktyabrskom_rajone/');
