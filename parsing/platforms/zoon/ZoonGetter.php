<?php


namespace parsing\platforms\zoon;


use parsing\platforms\Getter;
use phpQuery;


class ZoonGetter extends Getter {

    const LIMIT             =  8;
    const HOST              = 'https://zoon.ru';
    const ADD_PATH          = '/js.php?area=service&action=CommentList&owner%5B%5D=organization&owner%5B%5D=prof';
    const REQUEST_INFO      = '&is_widget=1&strategy_options%5Bwith_photo%5D=0&allow_comment=0&limit=';
    const PREFIX_ITERATOR   = '&skip=';

    protected $source;    // Информация, поступающая в getter извне
    protected $actual;
    protected $track;

    private $organization_path;     // Получаемая информация
    private $request = [7];
    private $last_review;

    public function __construct()
    {
        // todo: Рефакторинг создания реквеста при помощи массива, для того
        //      чтобы можно было гарантировать, что ничего не отвалится, при нарушении порядка.
        // Формирование массива, с частями url, чтобы в дальнейшем сформировать запрос
        $this->request[0] = self::HOST;
        $this->request[1] = self::ADD_PATH;
        $this->request[2] = self::REQUEST_INFO;
        $this->request[3] = self::LIMIT;
        $this->request[4] = self::PREFIX_ITERATOR;
        $this->request[5] = 0;

        $this->last_review = 0;
    }

    private function getOrganizationId() {
        $file = file_get_contents($this->source);
        $doc = phpQuery::newDocument($file);
        $this->organization_path = '&organization=' . $doc->find('.comments-section')->attr('data-owner-id');
    }

    public function getNextReviews() : string
    {
        $this->request[5] = $this->last_review++ * self::LIMIT;
        return file_get_contents(implode("", $this->request));
    }

    public function getAllReview()
    {
        // TODO: Implement getAllReview() method.
    }

    public function setSource($source)
    {
        $this->source = $source;
        $this->getOrganizationId();
        $this->request[6] = $this->organization_path;
    }

    public function setActual($actual)
    {
        $this->actual = $actual;
    }

    public function setTrack($track)
    {
        $this->track = $track;
    }

}