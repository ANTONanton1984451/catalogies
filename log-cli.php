<?php

 const DEBUG = 'debug';
 const INFO = 'info';
 const NOTICE = 'notice';
 const WARNING = 'warning';
 const ERROR = 'error';
 const CRITICAL = 'critical';
 const ALERT = 'alert';
 const EMERGENCY = 'emergency';

 const DELETE_ALL = 'all';

 const CODES = [
     DEBUG,
     INFO,
     NOTICE,
     WARNING,
     ERROR,
     CRITICAL,
     ALERT,
     EMERGENCY
 ];

    $argument = $argv[1];

    if(isset($argument) && in_array($argument,CODES)){

        $file = 'parsing/logger/log_files/'.$argument.'.log';
        if(file_exists($file)){
            unlink($file);
            echo "Лог ".$argument." успешно очищен";
        }else{
            echo "Данный лог уже очищен";
        }

    }elseif(isset($argument) && $argument = DELETE_ALL){

       foreach (CODES as $log){
           $file = 'parsing/logger/log_files/'.$log.'.log';
           @unlink($file);
       }
       echo "Все логи очищены";

    }else{
        echo "Введите не могу распознать команду";
    }

