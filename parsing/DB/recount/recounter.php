<?php
//Скрипт пересчёта коэффициентов
require_once '../../../vendor/autoload.php';

use Medoo\Medoo;

const BALANCE_COEFFICIENT = 4;
const ONE_DAY_IN_SEC = 86400;
const LIMIT = 1;

$offset = 0;
$now_sec = time();

$db = new Medoo([
    'database_type' => 'mysql',
    'database_name' => 'test',
    'server' => 'localhost',
    'username' => 'root',
    'password' => '',
    'option' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]
]);

while ($result = getReviews($db, $offset, LIMIT)) {

    setCoefficient($result, $now_sec);

    foreach ($result as $v) {
        $db->update('task_queue',
            ['review_per_day' => $v['review_per_day']],
            ['source_hash_key' => $v['source_hash_key']]
        );
    }
    $offset += LIMIT;
}



/**
 * @param array $res
 * @param int $time
 */
function setCoefficient(array &$res, int $time): void
{
    foreach ($res as &$arr) {
        $delta = $time - $arr['min_date'];
        $deltaDate = $delta / ONE_DAY_IN_SEC;
        $cof = $arr['count'] / $deltaDate;

        if ($cof > 6) {
            $cof = 6 * BALANCE_COEFFICIENT;
        } elseif ($cof < 1) {
            $cof = 1 * BALANCE_COEFFICIENT;
        } else {
            $cof = round($cof) * BALANCE_COEFFICIENT;
        }
        $arr['review_per_day'] = $cof;
        unset($arr['count'], $arr['min_date']);
    }
}

/**
 * @param Medoo $db
 * @param int $offset
 * @param int $limit
 * @return array
 */
function getReviews(Medoo $db, int $offset, int $limit): array
{
    return $db->query("SELECT min(date) as min_date,
                         count(id) as count,
                         `source_hash_key`
                         FROM `review` 
                         GROUP BY `source_hash_key`
                         LIMIT $limit
                         OFFSET $offset")
        ->fetchAll(\PDO::FETCH_ASSOC);
}




