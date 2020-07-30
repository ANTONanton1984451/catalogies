<?php
namespace parsing\platforms\zoon;

use parsing\platforms\Getter;
use phpQuery;

class ZoonGetter extends Getter {
    const LIMIT = 1;
    const HOST      = 'http://zoon.ru/';
    const ADD_PATH  = '/js.php?area=service&action=CommentList&owner%5B%5D=organization&owner%5B%5D=prof';

    private $link;
    private $organization_path;
    private $reviews = [];
    private $activeReview;

    public function __construct($link)
    {
        $this->link = $link;
        $this->prepareGetter();
    }

    public function prepareGetter()
    {
        $this->getOrganizationId();
        $this->getFirstReviews();
        //$this->activeReview = $this->reviews[0];
    }

    public function getOrganizationId() {
        $file = file_get_contents($this->link);
        $doc = phpQuery::newDocument($file);
        $this->organization_path = $doc->find('.comments-section')->attr('data-owner-id');
        $this->organization_path = '&organization=' . $this->organization_path;
    }

    public function getFirstReviews()
    {
        $request_info = '&is_widget=1&strategy_options%5Bwith_photo%5D=1&allow_comment=0&skip=127&limit=';

        $temporary = file_get_contents(self::HOST . self::ADD_PATH . $this->organization_path .
            $request_info . self::LIMIT
        );

        $data = json_decode($temporary);
        $tt = explode('"', $data->list);


        var_dump($data);

/*      $doc = phpQuery::newDocument($data->list);
        $temp = $doc->find('script')->attr("comment-text");
        $pq = pq($temp);
        var_dump($pq);*/

        //$this->clearData($data);

        }


    public function getNextReview()
    {
        // TODO: Implement getNextReview() method.
    }

    public function getNotifications()
    {
        // TODO: Implement getNotifications() method.
    }

    public function clearData($data){
        $doc = phpQuery::newDocument($data->list);
        $shortReview = $doc->find('#data-type');
        //$longReview = $doc->find('script');
        var_dump($shortReview);
        //var_dump($longReview);
    }

    public function getAllReview()
    {
        // TODO: Implement getAllReview() method.
    }
}