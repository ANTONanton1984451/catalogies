<?php


namespace parsing\platforms\topdealers;


use parsing\factories\factory_interfaces\FilterInterface;

class TopDealersFilter implements FilterInterface
{

    private $mainData = [];

    public function clearData($raw_data)
    {
        if(!empty($raw_data['reviews'])){
            $this->formReview($raw_data['reviews']);
        }

       $this->setMetaInfo($raw_data['meta_info']);
       $this->setConfig($raw_data['config']);

       return $this->mainData;
    }

    private function formReview(array $reviews):void
    {
        foreach ($reviews as $v){
            $oneReview['tonal']      = $v['tonal'];
            $oneReview['text']       = json_encode(['title'=>$v['title'],'text'=>$v['text']]);
            $oneReview['identifier'] = $v['identifier'];
            $oneReview['date']       = $v['date'];
            $oneReview['rating']     = $this->tonalToRating($v['tonal']);
            $oneReview['platform']   = 'topdealers';

            $this->mainData['reviews'][] = $oneReview;
        }

    }
    private function tonalToRating(string $tonal):int
    {
        switch ($tonal){
            case 'Положительный':
                $a = 5;
                break;
            case 'Нейтральный':
                $a = 4;
                break;
            case 'Отрицательный':
                $a = 3;
                break;
            default:
                $a = -1;
        }
        return $a;
    }
    private function setMetaInfo(array $meta_info):void
    {
        $this->mainData['meta_info'] = json_encode($meta_info);
    }
    private function setConfig(array $config):void
    {
        $this->mainData['config'] = $config;
    }

}