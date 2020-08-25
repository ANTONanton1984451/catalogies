<?php

use parsing\ParserManager;
use parsing\platforms\zoon\ZoonGetter;
use parsing\platforms\zoon\ZoonFilter2;
use parsing\platforms\zoon\ZoonModel;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;

require_once "autoloader.php";
require_once "vendor/autoload.php";

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);


//$logger = new Logger('dev');
//// Now add some handlers
//$logger->pushHandler(new StreamHandler(__DIR__.'/my_dev.log', Logger::DEBUG));
//$logger->pushHandler(new FirePHPHandler());
//
//// You can now use your logger
//$logger->

\parsing\LoggerManager::init();

\parsing\LoggerManager::log('test','test',['data'=>'test']);

['source'=>'',
  'config'=>['token_info'=>['конфиги токена']]  ];