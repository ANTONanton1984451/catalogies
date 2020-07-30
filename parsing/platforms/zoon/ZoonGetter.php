<?php
namespace parsing\platforms\zoon;

use parsing\platforms\Getter;
use phpQuery;

class ZoonGetter extends Getter {
    const LIMIT = 1;

    private $link;
    private $organization_id;
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
        //$this->lastReview = $this->reviews[0];
    }

    public function getOrganizationId() {
        $file = file_get_contents($this->link);
        $doc = phpQuery::newDocument($file);
        $this->organization_id = $doc->find('.comments-section')->attr('data-owner-id');

    }

    public function getFirstReviews()
    {
        $host =     'http://zoon.ru/';
        $add_path = '/js.php?area=service&action=CommentList&owner%5B%5D=organization&owner%5B%5D=prof&organization=';
        $request_info = '&is_widget=1&strategy_options%5Bwith_photo%5D=1&allow_comment=0&skip=127&limit=';

        $temporary = file_get_contents
        (
            $host.
            $add_path.
            $this->organization_id.
            $request_info.
            self::LIMIT
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
}