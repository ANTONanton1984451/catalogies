<?php
// todo: Сделать сравнение данных при обычном парсинге через повторение js скрипта, и при помощи браузера
// todo: Изучить тему, связанную с использованием прокси, и избеганием бана от площадок.

// todo: Проверка на правильный ответ от сервера
// todo: Сделать проверку сохранненого хэша с полученным. Если совпадают, то отправлять meta-info
//          (т.к. meta info может измениться)

namespace parsing\platforms\zoon;

use parsing\factories\factory_interfaces\GetterInterface;
use phpQuery;

class ZoonGetter implements GetterInterface
{
    const REVIEWS_LIMIT     =  100;

    const STATUS_REVIEWS        = 0;
    const STATUS_SOURCE_REVIEW  = 1;
    const STATUS_END            = 2;

    const HANDLED_TRUE      = 'HANDLED';

    const HOST              = 'https://zoon.ru/js.php?';
    const PREFIX_SKIP       = 'skip';

    const QUERY_CONST_DATA  = [
            'area'                                  => 'service',
            'action'                                => 'CommentList',
            'owner[]'                               => 'organization',
            'is_widget'                             => 1,
            'strategy_options[with_photo]'          => 0,
            'allow_comment'                         => 0,
            'allow_share'                           => 0,
            'limit'                                 => self::REVIEWS_LIMIT,
    ];

    private $status;                // Статус работы Getter'a

    protected $source;              // Информация, поступающая в Getter из Controller'a
    protected $handled;             // Флаг, который обозначает, обрабатывалась ли ссылка ранее

    private $add_query_info = [];   // Дополнительная информация для url запроса
    private $active_list_reviews;   // Номер последнего обработанного листа с отзывами



    public function __construct() {
        $this->add_query_info['owner[]']    = 'prof';      // Строка нужна для корректного формирования url запроса
        $this->active_list_reviews          = 0;
        $this->status                       = self::STATUS_REVIEWS;
    }
    public function setConfig($config) : void {
        $this->handled  = $config['handled'];
        $this->source   = $config['source'];
        $this->getOrganizationId();
    }
    private function getOrganizationId() : void {
        $file = file_get_contents($this->source);
        $document = phpQuery::newDocument($file);
        $this->add_query_info['organization'] = $document->find('.comments-section')->attr('data-owner-id');
        phpQuery::unloadDocuments();
    }



    public function getNextRecords() {
        switch ($this->status) {
            case self::STATUS_REVIEWS:
                $records = $this->getReviews();
                if ($records->list === "") {
                    $records = $this->getMetaInfo();
                }
                break;

            case self::STATUS_SOURCE_REVIEW:
                $records = $this->getMetaInfo();
                break;

            case self::STATUS_END;
                $records = $this->getEndCode();
                break;
        }

        return $records;
    }
    private function getReviews(){
        $this->add_query_info[self::PREFIX_SKIP] = $this->active_list_reviews++ * self::REVIEWS_LIMIT;
        $data = file_get_contents
        (self::HOST
            . http_build_query(self::QUERY_CONST_DATA)
            . '&' . http_build_query($this->add_query_info)
        );
        $records = json_decode($data);

        if ($records->remain == 0 || $this->handled === self::HANDLED_TRUE) {
            $this->status = self::STATUS_SOURCE_REVIEW;
        }

        return $records;
    }
    private function getMetaInfo() {
        $file = file_get_contents($this->source);
        $document = phpQuery::newDocument($file);

        $countReviews = $document->find('.fs-large.gray.js-toggle-content')->text();
        $records['count_reviews'] = explode(' ', trim($countReviews))[0];
        $records['average_mark']  = $document->find('span.rating-value')->text();

        phpQuery::unloadDocuments();

        $this->status = self::STATUS_END;

        return $records;
    }
    private function getEndCode() {
        return self::END_CODE;
    }
}