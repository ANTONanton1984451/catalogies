<?php

define("DATABASE",'test');
define("DB_USER",'phpmyadmin');
define("DB_PASSWORD",'some_pass');

define("NEW_WORKER", 0);
define("HIGH_PRIORITY_WORKER", 1);
define("LOW_PRIORITY_WORKER", 2);

define ("HIGH_PRIORITY_PLATFORMS", [
    "'google'",
    "'zoon'"
]);

define ("LOW_PRIORITY_PLATFORMS", [
    "'topdealers'",
    "'yell'"
]);