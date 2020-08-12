<?php

require_once "vendor/autoload.php";
require_once "autoloader.php";

use parsing\DbController;

if (isset($argv[1])) {
    $controller = new DbController();

    switch ($argv[1]) {
        case 'createTables':
            $controller->createTables();
            break;

        case 'dropTables':
            $controller->dropTables();
            break;

        case 'updateTables':
            $controller->dropTables();
            $controller->createTables();
            break;

        case 'seedMyDb':
            $controller->seedDB();
            break;

        default:
            echo 'Unknown command';
    }
} else {
    echo 'What do you want?';
    exit();
}
