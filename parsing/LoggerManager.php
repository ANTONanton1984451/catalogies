<?php

namespace parsing;

use http\Cookie;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;
class LoggerManager
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const NOTICE = 'notice';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const CRITICAL = 'critical';
    public const ALERT = 'alert';
    public const EMERGENCY = 'emergency';

    private const MESSAGE_CODES = [
        self::DEBUG,
        self::INFO,
        self::NOTICE,
        self::WARNING,
        self::ERROR,
        self::CRITICAL,
        self::ALERT,
        self::EMERGENCY,
    ];

    private static $logger;

    public static function init():void
    {
        self::$logger = new Logger('dev');
        echo dirname(__DIR__) . '/my_dev.log';
        self::$logger->pushHandler(new StreamHandler(dirname(__DIR__) . '/my_dev.log', Logger::DEBUG));
        self::$logger->pushHandler(new FirePHPHandler());
    }

    public static function log(string $log_code_name,string $message = '' , array $context = []):void
    {
            if(!in_array($log_code_name,self::MESSAGE_CODES)){
                throw new \Exception('No such message level');
            }
            self::$logger->$log_code_name($message,$context);
    }
}