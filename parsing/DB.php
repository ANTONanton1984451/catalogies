<?php


namespace parsing;
use parsing\traits\Singleton;

/**
 * Class DB
 * @package parsing
 * Примечание:т.к. используется трейт синглтон,то PHPSTORM не даёт подсказки по методам.
 * @todo Возможно можно запихнуть этот класс в Pimple и не заботиться о синглтоне
 */
class DB
{
    use Singleton;

    const FETCH_ALL=1;
    const FETCH_COLUMN=2;
    const COMPLETE='COMPLETE';
    const WAIT='WAIT';
    const ALL='all';

    private $dbname='doctrine';
    private $host='localhost';
    private $user='root';
    private $password='';
    private $charset='utf8';
    private $pdo;

    private  $options = [
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // по умолчанию ассоциативный массив
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION       // ошибки бросают исключения
    ];

    private function __construct()
    {
        $this->pdo=new \PDO('mysql:host='.$this->host.
                                ';dbname='.$this->dbname.
                                ';charset='.$this->charset,
                                $this->user,
                                $this->password,
                                $this->options);

    }

    /**
     * @param array $reviews
     * @param string $hash
     * @return void
     * Функция кладёт отзывы и доп. инфу в БД.
     * Использует транзакции,так что,если в БД  положенн отзыв но почему-то не положена доп. инфа,
     * то в таблицу не положится текущая строка в иттерации цикла
     * Формат элемента массива,который метод понимает(identifier не обязательный параметр):
     * ['platform'=>'yandex','text'=>'fasdfsafd','rating'=>2,'tonal'=>'POSITIVE','date'=>11234,'identifier'=>'test']
     */

    public function insertReview(array $reviews, string $hash):void
    {
       $queryReview = $this->pdo->prepare('INSERT INTO `review`(
                                                            `platform`,
                                                            `source_hash_key`,
                                                            `text`,
                                                            `rating`,
                                                            `tonal`,
                                                            `date`
                                                            )
                                                            VALUES(
                                                            :platform,
                                                            :source_hash,
                                                            :text,
                                                            :rating,
                                                            :tonal,
                                                            :date
                                                            )  
                                    ');

        $queryAddInfoReview = $this->pdo->prepare('INSERT INTO `add_info_review`(
                                                                                   `review_id`,
                                                                                   `identifier` 
                                                                                    )
                                                                                    VALUES(
                                                                                    :rew_id,
                                                                                    :identifier
                                                                                    )
                                            ');


            foreach ($reviews as $v){
               $this->pdo->beginTransaction();
               try{
                   $queryReview->bindParam(':platform',$v['platform']);
                   $queryReview->bindParam(':source_hash',$hash);
                   $queryReview->bindParam(':text',$v['text']);
                   $queryReview->bindParam(':rating',$v['rating'],\PDO::PARAM_INT);
                   $queryReview->bindParam(':tonal',$v['tonal']);
                   $queryReview->bindParam(':date',$v['date'],\PDO::PARAM_INT);

                   $queryReview->execute();


                   if(!empty($v['identifier'])){
                       $rew_id = $this->pdo->lastInsertId();

                       $queryAddInfoReview->bindParam(':rew_id',$rew_id,\PDO::PARAM_INT);
                       $queryAddInfoReview->bindParam(':identifier',$v['identifier']);

                       $queryAddInfoReview->execute();

                   }
               }catch (\PDOException $e){
                    $this->pdo->rollBack();
                    continue;
               }
               $this->pdo->commit();


            }

    }


    /**
     * @param int $limit
     * @return array
     * Получает таски и нужную инфу для их выполнения,
     * можно указать лимит получаемых тасок,по дефолту стоит 5
     */
    public function getTasks(int $limit=5):array
    {
        $tasks=$this->pdo->prepare('SELECT `source_review`.`platform`,
                                                     `source_review`.`source`,
                                                     `source_review`.`source_config`,
                                                     `source_review`.`track`,
                                                     `source_review`.`handled`,
                                                     `source_review`.`source_hash`
                                                     FROM `task_queue`
                                                     INNER JOIN `source_review` ON 
                                                     `source_review`.`source_hash`=`task_queue`.`source_hash_key`
                                                     WHERE `source_review`.`actual`= :actual AND `task_queue`.`status`= :status
                                                     LIMIT :limit');
        $tasks->bindParam(':limit',$limit,\PDO::PARAM_INT);
        $tasks->bindValue(':actual','ACTIVE');
        $tasks->bindValue(':status','WAIT');
        $tasks->execute();
        return $tasks->fetchAll();
    }

    /**
     * @param string $hash
     * @param bool $add
     * @param int $fetch
     * @return array
     * @throws \Exception
     * Возвращает все отзывы по хэшу,при установке флага $add в положение
     * false выдаёт отзывы без доп.информации к ней,при установке флага  в положение true
     *  добавляется информация из таблицы 'review_add_info'
     */
    public function selectAllReview(string $hash, $add = true,$fetch = self::FETCH_ALL) : array
    {
        $sql = 'SELECT * FROM `review`';
        if($add){
            $sql.=' LEFT JOIN `add_info_review` ON `review`.`id`=`add_info_review`.`review_id`';
        }

        $sql.='WHERE `source_hash_key`= :hash';

        $queryReview=$this->pdo->prepare($sql);
        $queryReview->bindParam(':hash',$hash);
        $queryReview->execute();

        switch ($fetch){
            case self::FETCH_ALL :
                 return $queryReview->fetchAll();
                 break;
            case self::FETCH_COLUMN :
                 return $queryReview->fetchAll(\PDO::FETCH_COLUMN);
                 break;
            default:
                 throw new \Exception('no such flag');
        }

    }

    /**
     * @param string $status
     * @param string $where
     * @return int
     * Функция меняет статус на "выполнено" или "невыполнено"
     * Она может обновить по всей таблице или по отдельному хэшу
     * Если неуказаны все параметры,то всем таскам ставится статус "выполнено"
     * Возвращает количество обновлённых строк
     */
    public function setTaskStatus(string $status=self::COMPLETE,string $where=self::ALL):int
    {

        if($where === self::ALL){
            $statusQuery=$this->pdo->prepare('UPDATE `task_queue` SET `status` = :status');
        }else{

             $statusQuery=$this->pdo->prepare('UPDATE `task_queue` SET `status` = :status 
                                                          WHERE `task_queue`.`source_hash_key`= :hash');

            $statusQuery->bindParam(':hash',$where);
        }


        $statusQuery->bindParam(':status',$status);
        $statusQuery->execute();
        return $statusQuery->rowCount();
    }

    /**
     * @param string $hash
     * @param int $handle_status
     * @return int
     * Меняет статус handle в таблице 'source_review' по заданному хэшу.
     * Возвращает колчество затронутых строк
     */
    public function setHandle(string $hash,int $handle_status):int
    {
        $handleQuery=$this->pdo->prepare('UPDATE `source_review` SET `handled`=:handle
                                                    WHERE  `source_hash`=:hash');

        $handleQuery->bindParam(':handle',$handle_status,\PDO::PARAM_INT);
        $handleQuery->bindParam(':hash',$hash);
        $handleQuery->execute();

        return $handleQuery->rowCount();
    }





}