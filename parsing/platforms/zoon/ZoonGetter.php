<?php
// todo: сделать сравнение данных при обычном парсинге через повторение js скрипта, и при помощи браузера


namespace parsing\platforms\zoon;


use parsing\platforms\Getter;
use phpQuery;


class ZoonGetter extends Getter {
    const REVIEWS_LIMIT     =  2;

    const HOST              = 'https://zoon.ru/js.php?';
    const PREFIX_SKIP       = 'skip';

    const QUERY_CONST_DATA  = [
                                'area'                                  => 'service',
                                'action'                                => 'CommentList',
                                'owner[]'                               => 'organization',
                                'is_widget'                             => 1,
                                'strategy_options[with_photo]'          => 0,
                                'allow_comment'                         => 0,
                                'limit'                                 => self::REVIEWS_LIMIT,
                              ];



    protected $source;      // Информация, поступающая в getter из Controller'a
    protected $track;       // Какие отзывы отслеживаем
    protected $handled;     // Обрабатывалась ли ссылка ранее

    private $add_query_info = [];   // Дополнительная информация для url запроса
    private $active_list_reviews;   // Номер текущего листа с отзывами
    private $status;

    public function __construct()
    {
        $this->add_query_info['owner[]'] = 'prof';      // Строка нужна для корректного формирования url запроса
        $this->active_list_reviews = 0;
    }

    public function getNextReviews() : string
    {
        $this->add_query_info[self::PREFIX_SKIP] = $this->active_list_reviews++ * self::REVIEWS_LIMIT;

        // todo: Сделать генерацию end message'a
        if ($this->status == 'end')
        {
            return 'end message';
        }

        // todo: Проверка при генерации ответа, на правильные данные

        return file_get_contents
        (
            self::HOST
            . http_build_query(self::QUERY_CONST_DATA)
            . '&'
            . http_build_query($this->add_query_info)
        );
    }

    public function setSource($source) : void
    {
        $this->source = $source;
        $this->getOrganizationId();
    }

    public function setActual($actual) : void
    {
        $this->actual = $actual;
    }

    public function setTrack($track) : void
    {
        $this->track = $track;
    }

    private function getOrganizationId() : void
    {
        $file = file_get_contents($this->source);
        $doc = phpQuery::newDocument($file);
        $this->add_query_info['organization'] = $doc->find('.comments-section')->attr('data-owner-id');
        phpQuery::unloadDocuments();
    }

}