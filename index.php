<?php
require_once "autoloader.php";
use parsing\DB;

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$db=DB::getInstance();
$hash='gfdgsgfs';
//$review1=['platform'=>'yandex','text'=>'fasdfsafd','rating'=>4,'tonal'=>'POSITIVE','date'=>11234];
//$review2=['platform'=>'yandex','text'=>'fasdfsafd','rating'=>2,'tonal'=>'POSITIVE','date'=>11234,'identifier'=>'test'];
//$review[]=$review1;
//$review[]=$review2;
echo $db->setHandle('gfdgsgfs',0);
