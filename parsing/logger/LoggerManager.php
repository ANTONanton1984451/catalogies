<?php

namespace parsing\logger;

use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;

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

    private const OUTPUT_FORMAT = "%datetime% | %channel%.%level_name% | message : %message% | %context% | %extra% \n";
    private const DATE_FORMAT =  "Y:n:j T g:i a";

    /**
     * Инициализация Логгера.
     * Логеру устанавливается формат записи и два обработчика.
     * Следом инициализируются слушатели всех видов сообщений и сам логер заполняется ими
     */
    public static function init():void
    {
        self::$logger = new Logger('dev');
        self::$logger->pushProcessor(new MemoryUsageProcessor());
        self::$logger->pushProcessor(new MemoryPeakUsageProcessor());
        self::initStreams();

        $formatter = new LineFormatter(self::OUTPUT_FORMAT,self::DATE_FORMAT);

        foreach (self::$streams as $stream){
            $stream->setFormatter($formatter);
            self::$logger->pushHandler($stream);
        }

        self::$logger->pushHandler(new FirePHPHandler());
    }


    /**
     * @param string $log_code_name код записи(смотри константы)
     * @param string $message сообщение в записи
     * @param array $context переменные контекста,где произведена запись в лог.Можно использовать и объекты
     * @throws \Exception в случае неверного кода записи
     * Особенность записи в лог состоит в том,что объекты перед сериализацией экранируются.
     * В связи с этим перед испоьзованием сериализированного объекта его нужно "разэкранировать"
     */
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

    /**
     * Инициализация слушателей осообщений
     * Аргументы в конструкторе :
     * 1.$stream - путь до нужного лога
     * 2.$level -минимальный  уровень сообщения
     * 3.$bubble - всплывать сообщению или нет.В случае true ,то сообщения типа EMERGENCY будут записываться во все логи,
     * сообщения типа ALERT будут записываться во все логи,кроме EMERGENCY и так далее
     *
     */
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