<?php

namespace parsing\platforms\yell;

use parsing\factories\factory_interfaces\GetterInterface;
use phpQuery;

class YellGetter implements GetterInterface
{
    const STATUS_REVIEWS        = 0;
    const STATUS_END            = 1;

    const HOST = 'https://www.yell.ru/company/reviews/?';

    private $status;
    private $source;

    private $addQueryInfo;
    private $activeList;

    public function __construct() {
        $this->status = self::STATUS_REVIEWS;

        $this->addQueryInfo =   ['sort' => 'recent'];
        $this->activeList   =   1;
    }

    public function setConfig($config) {
        $this->source = $config['source'];
        $this->getOrganizationId();
    }

    public function getNextRecords() {
        switch ($this->status) {
            case self::STATUS_REVIEWS:
                $records = $this->getReviews();
                if (strlen($records) == 64) {
                    $records = $this->getMetaInfo();
                }
                break;

            case self::STATUS_END:
                $records = $this->getEndCode();
                break;
        }

        return $records;
    }

    private function getReviews() {
        $records = file_get_contents(self::HOST . http_build_query($this->addQueryInfo));
        $this->addQueryInfo['page'] = $this->activeList++;
        return $records;
    }

    private function getEndCode() {
        return self::END_CODE;
    }

    private function getOrganizationId() : void {
        $source_page = file_get_contents($this->source);
        $doc = phpQuery::newDocument($source_page);
        $this->addQueryInfo['id'] = $doc->find('.company.company_default')->attr('data-id');
        phpQuery::unloadDocuments();
    }

    private function getMetaInfo() {
        return 'meta';
    }
}