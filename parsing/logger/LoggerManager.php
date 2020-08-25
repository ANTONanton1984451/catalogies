<?php

namespace parsing\logger;


use Monolog\Formatter\HtmlFormatter;
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
    private static $streams = [];

    public static function init():void
    {
        self::$logger = new Logger('dev');

        self::initStreams();

        foreach (self::$streams as $stream){
            self::$logger->pushHandler($stream);
        }

        self::$logger->pushHandler(new FirePHPHandler());
    }



    public static function log(string $log_code_name,string $message = '' , array $context = []):void
    {
            if(!in_array($log_code_name,self::MESSAGE_CODES)){
                throw new \Exception('No such message level');
            }
            foreach ($context as &$v){
                if(is_object($v)){
                    $v = addslashes(serialize($v));
                    echo "Произошла сериализация";
                }
            }
            self::$logger->$log_code_name($message,$context);
    }


    private static function initStreams():void
    {
        self::$streams[]  = new StreamHandler(dirname(__DIR__).'/logger/log_files/debug.log',
                                                Logger::DEBUG,
                                                false);


        self::$streams[] = new StreamHandler(dirname(__DIR__).'/logger/log_files/info.log',
                                                  Logger::INFO,
                                                 false);

        self::$streams[] = new StreamHandler(dirname(__DIR__).'/logger/log_files/notice.log',
                                                  Logger::NOTICE,
                                                false);

        self::$streams[] = new StreamHandler(dirname(__DIR__).'/logger/log_files/warning.log',
                                                Logger::WARNING,
                                                false);

        self::$streams[] = new StreamHandler(dirname(__DIR__).'/logger/log_files/error.log',
                                                Logger::ERROR,
                                                false);

        self::$streams[] = new StreamHandler(dirname(__DIR__).'/logger/log_files/critical.log',
                                                Logger::CRITICAL,
                                                false);

        self::$streams[] = new StreamHandler(dirname(__DIR__).'/logger/log_files/alert.log',
                                                Logger::ALERT,
                                                false);

        self::$streams[] = new StreamHandler(dirname(__DIR__).'/logger/log_files/emergency.log',
                                                Logger::EMERGENCY,
                                                false);
    }
}