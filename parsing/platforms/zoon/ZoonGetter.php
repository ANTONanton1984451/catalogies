<?php
// todo: Сделать сравнение данных при обычном парсинге через повторение js скрипта, и при помощи браузера
// todo: Проверка при генерации ответа, на правильные данные

// todo: Цеплять общую оценку заведения и количества отзывов, чтобы была возмонжость строить статистику в динамике.
// todo: Изучить тему, связанную с использованием прокси, и избеганием бана от площадок.


namespace parsing\platforms\zoon;

use parsing\factories\factory_interfaces\GetterInterface;
use phpQuery;
use Exception;

class ZoonGetter implements GetterInterface
{
    const REVIEWS_LIMIT     =  50;

    const STATUS_OVER       = 0;
    const STATUS_ACTIVE     = 1;
    const STATUS_END        = 2;

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
    protected $track;               // Флаг, который обозначает, какие отзывы мы отслеживаем по данной ссылке
    protected $handled;             // Флаг, который обозначает, обрабатывалась ли ссылка ранее

    private $add_query_info = [];   // Дополнительная информация для url запроса
    private $active_list_reviews;   // Номер последнего обработанного листа с отзывами

    public function __construct() {
        $this->add_query_info['owner[]']    = 'prof';      // Строка нужна для корректного формирования url запроса
        $this->active_list_reviews          = 0;
        $this->status                       = self::STATUS_ACTIVE;
    }

    public function getNextReviews() {
        if ($this->status === self::STATUS_END || $this->status === self::STATUS_OVER) {
            $data = self::END_CODE;
        }

        if ($this->status == self::STATUS_ACTIVE) {

            $this->add_query_info[self::PREFIX_SKIP] = $this->active_list_reviews++ * self::REVIEWS_LIMIT;
            $data = file_get_contents
            (self::HOST
                . http_build_query(self::QUERY_CONST_DATA)
                . '&' . http_build_query($this->add_query_info)
            );
            $data = json_decode($data);

            if ($data->remain == 0 && $data->list == '') {
                $this->status = self::STATUS_END;
            } elseif ($data->remain == 0) {
                $this->status = self::STATUS_OVER;
            }
        }

        if (isset($data)) {
            return $data;
        } else {
            throw new Exception("Ответ не был получен");
        }
    }

    private function getOrganizationId() : void {
        $file = file_get_contents($this->source);
        $doc = phpQuery::newDocument($file);
        $this->add_query_info['organization'] = $doc->find('.comments-section')->attr('data-owner-id');
        phpQuery::unloadDocuments();
    }

    public function setConfig($config) : void {
        $this->handled  = $config['handled'];
        $this->source   = $config['source'];
        $this->track    = $config['track'];

        $this->getOrganizationId();
    }
}