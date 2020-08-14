<?php

use parsing\ParserManager;

require_once "autoloader.php";
require_once "vendor/autoload.php";

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

//$manager = new ParserManager();
//$manager->parseSources();

setSource();

function setSource() {
    $source = 'https://volgograd.zoon.ru/shops/kulinariya_konfetki-baranochki_na_prospekte_lenina-9fcd/';
    $db = new \parsing\DB\DatabaseShell();
    $db->insertSourceReview([
        'source_hash'   =>  md5($source),
        'platform'      =>  'zoon',
        'source'        =>  $source,
        'actual'        =>  'ACTIVE',
        'track'         =>  'ALL',
        'handled'       =>  'NEW'
    ]);
}