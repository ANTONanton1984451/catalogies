<?php

use parsing\ParserManager;
use parsing\platforms\zoon\ZoonGetter;
use parsing\platforms\zoon\ZoonFilter2;
use parsing\platforms\zoon\ZoonModel;
use parsing\logger\LoggerManager;


require_once "autoloader.php";
require_once "vendor/autoload.php";

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
$test = unserialize(stripslashes("O:5:\\\"Moack\\\":3:{s:8:\\\"\\0Moack\\0a\\\";i:1;s:4:\\\"\\0*\\0b\\\";i:2;s:1:\\\"c\\\";i:3;}"));
var_dump($test);
$mock = new Moack();

LoggerManager::init();

LoggerManager::log(LoggerManager::ALERT,'Test debug',['data'=>$mock,'track'=>'test']);

