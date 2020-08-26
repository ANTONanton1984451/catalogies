<?php
require_once "autoloader.php";
require_once "vendor\autoload.php";

use parsing\logger\LoggerManager;

LoggerManager::init();
LoggerManager::log(LoggerManager::DEBUG,'test',['data'=>'test']);