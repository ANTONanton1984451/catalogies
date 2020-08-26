<?php
// todo: Сделать проверку, и найти гарантированный способ доставать id сайта

namespace parsing\platforms\yell;

use parsing\factories\factory_interfaces\GetterInterface;
use phpQuery;

class YellGetter implements GetterInterface
{
    const STATUS_ACTIVE = 0;
    const STATUS_END = 1;

    const HOST = 'https://www.yell.ru/company/reviews/?';
    const EMPTY_RECORD = 64;

    private $status;
    private $activePage;

    private $source;
    private $handled;
    private $oldHash;

    private $queryInfo;
    private $metaRecord;


    public function __construct()
    {
        $this->status = self::STATUS_ACTIVE;
        $this->activePage = 1;

        $this->queryInfo = [
            'sort' => 'recent',
            'page' => $this->activePage,
        ];
    }

    public function setConfig($config) : void
    {
        $this->source =  $config['source'];
        $this->handled = $config['handled'];
        $this->oldHash = json_decode($config['source_config'])['oldHash'];
        $this->getOrganizationId();
    }

    private function getOrganizationId() : void
    {
        $sourcePage = file_get_contents($this->source);
        $document = phpQuery::newDocument($sourcePage);

        $this->queryInfo['id'] = $document->find('div.company.company_paid')->attr('data-id');

        $this->metaRecord['average_mark'] = $document->find('div.company__rating span.rating__value')->text();
        $this->metaRecord['count_reviews'] = $document->find('span.rating__reviews span')->text();

        phpQuery::unloadDocuments();
    }



    public function getNextRecords()
    {
        if ($this->status === self::STATUS_END) {
            $records = $this->getEndCode();
        }

        if ($this->status === self::STATUS_ACTIVE) {
            $records = $this->getReviews();

            if (strlen($records) === self::EMPTY_RECORD) {
                $records = $this->getMetaInfo();
                $this->status = self::STATUS_END;
            }
        }

        return $records;
    }

    private function getReviews()
    {
        if ($this->handled === 'NEW') {
            $records = file_get_contents(self::HOST . http_build_query($this->queryInfo));

            if ($this->activePage == 1) {
                $this->metaRecord['old_hash'] = md5($records);
            }

            $this->queryInfo['page'] = $this->activePage++;
        }

        if ($this->handled === 'HANDLED') {
            $records = file_get_contents(self::HOST . http_build_query($this->queryInfo));
            if ($this->isEqualsHash(md5($records))) {
                $records = self::EMPTY_RECORD;
            }
        }

        return $records;
    }

    private function getMetaInfo()
    {
        return $this->metaRecord;
    }

    private function getEndCode()
    {
        return self::END_CODE;
    }

    private function isEqualsHash(string $md5): bool
    {
        return $this->oldHash === $md5;
    }
}