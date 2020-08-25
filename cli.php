<?php

require_once "vendor/autoload.php";
require_once "autoloader.php";

use parsing\DB\MigrationsManager;

if (isset($argv[1])) {
    $controller = new MigrationsManager();

    switch ($argv[1]) {
        case 'createSchema':
            $controller->createSchema();
            echo "Create Schema is ready \n";
            break;

        case 'updateSchema':
            $controller->dropTables();
            $controller->createSchema();
            echo "Update Schema is ready \n";
            break;

        case 'dropTables':
            $controller->dropTables();
            echo "Drop Tables is ready \n";
            break;

        default:
            echo "Unknown command \n";
    }
} else {
    echo "Enter parameter... \n";
    exit();
}
