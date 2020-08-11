<?php

namespace parsing\platforms\yell;

use parsing\factories\factory_interfaces\GetterInterface;
use phpQuery;

class YellGetter implements GetterInterface
{
    const END_CODE = 42;

    const HOST = 'https://www.yell.ru/company/reviews/?';

    private $active_list_reviews;
    private $add_query_info = ['sort' => 'recent'];

    protected $source;      // Информация, поступающая в getter из Controller'a
    protected $track;       // Какие отзывы отслеживаем
    protected $handled;     // Обрабатывалась ли ссылка ранее

    public function __construct() {
        $this->active_list_reviews = 1;
        $this->add_query_info['page'] = $this->active_list_reviews;
    }

    public function getNextReviews() {
        $data = file_get_contents(self::HOST . http_build_query($this->add_query_info));
        if (strlen($data) == 64) {
            $data = self::END_CODE;
        }

        $this->add_query_info['page'] = $this->active_list_reviews++;

        return $data;
    }

    public function setConfig($config) : void {
        $this->handled  = $config['handled'];
        $this->source   = $config['source'];
        $this->track    = $config['track'];
        $this->getOrganizationId();
    }

    private function getOrganizationId() : void {
        $source_page = file_get_contents($this->source);
        $doc = phpQuery::newDocument($source_page);
        $this->add_query_info['id'] = $doc->find('.company.company_paid')->attr('data-id');
        phpQuery::unloadDocuments();
    }
}