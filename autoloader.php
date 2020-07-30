<?php
/**
 * Регистрация автозагрузчика,
 * смотри https://www.php.net/manual/en/function.spl-autoload-register.php
 */
spl_autoload_register(function ($className){
    $fileName = __DIR__ .'/'  .str_replace('\\','/',$className) . '.php';
    if(file_exists($fileName)) {
        require_once $fileName;
    }
});