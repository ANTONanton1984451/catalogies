<?php
require_once "autoloader.php";
require_once 'vendor/autoload.php';
use parsing\DB;

$time1=time();



ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$config=['token_info'=>['access_token'=>'ya29.a0AfH6SMB5aHEUJHmPxhSD-bEfJ1dnBZyDlcpsWfQwOu7dyIEAbmBj6zkeRpVsbr6C3S1_IsT2cxmAupo8kgj6h84dYrmoeDeDWvqW2AUAsa70EfOYlFdrIVGeFiXQQM2fyh0IK84DntaLJJu4D-AecKbF989er0_EHxI',
                        'expires_in'=>3599,
                        'refresh_token'=>'1//0cTctGxAwM_5tCgYIARAAGAwSNwF-L9IrOBVaIf1Xu8bFkqB-Sb2d6-Z7fAdYY_hPOvvYn-mJH4OERjMlWGIg6AQnoC4JM0V-RTM',
                        'scope'=>'https://www.googleapis.com/auth/business.manage',
                        'token_type'=>'Bearer',
                        'created'=>1596125763],
        'last_review_hash'=>'584249f74fa83fb3f8561cd37509ddba',
        'last_review_date'=>1596521396];
$trigger = 'start';

$getter=new \parsing\platforms\google\GoogleGetter(new Google_Client());
$filter=new \parsing\platforms\google\GoogleFilter;

$getter->setTrack('all');

$getter->setSource('accounts/101148201288830043360/locations/5839617167530752762');

$getter->setConfig($config);

while($trigger === 'start'){

    $buffer = $getter->getNextReviews('NEW');

    if($buffer['trigger'] === 'end'){
        $trigger = 'end';
        continue;
    }

   $buffer=$filter->clearData($buffer);
   var_dump($buffer);
}


