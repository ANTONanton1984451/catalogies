<?php

require_once "vendor/autoload.php";
require_once "autoloader.php";

use parsing\DB\MigrationsManager;

if (isset($argv[1])) {
    $controller = new MigrationsManager();

    switch ($argv[1]) {
        case 'createSchema':
            $controller->createSchema();
            break;

        case 'updateSchema':
            $controller->dropTables();
            $controller->createSchema();
            break;

        case 'dropTables':
            $controller->dropTables();
            break;

        default:
            echo 'Unknown command';
    }
} else {
    echo 'Enter parameter...';
    exit();
}
