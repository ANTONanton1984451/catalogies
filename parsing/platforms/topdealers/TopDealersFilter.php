<?php


namespace parsing\platforms\topdealers;


use parsing\factories\factory_interfaces\FilterInterface;

class TopDealersFilter implements FilterInterface
{

    private $mainData = [];

    /**
     * @param $records
     * @return array
     * Преобразовывает данные в приемлемый вид
     */
    public function clearData($records)
    {
        if(!empty($records['reviews'])){
            $this->formReview($records['reviews']);
        }

       $this->setMetaInfo($records['meta_info']);
       $this->setInnerConfig($records['config']);

       return $this->mainData;
    }

    /**
     * @param array $reviews
     * Форматирует отзывы
     */
    private function formReview(array $reviews):void
    {
        foreach ($reviews as $v){

            $oneReview['tonal']      = $this->tonalToEnum($v['tonal']);
            $oneReview['text']       = json_encode(['title'=>$v['title'],'text'=>$v['text']]);
            $oneReview['identifier'] = $v['identifier'];
            $oneReview['date']       = $v['date'];
            $oneReview['rating']     = $this->tonalToRating($v['tonal']);
            $this->mainData['reviews'][] = $oneReview;
        }

    }

    /**
     * @param string $tonal
     * @return string
     * Переводит тональность к ENUM виду в таблице
     */
    private function tonalToEnum(string $tonal):string
    {
        $enum = '';
        switch ($tonal){
            case 'Положительный':
                $enum = 'POSITIVE';
                break;
            case 'Нейтральный':
                $enum = 'NEUTRAL';
                break;
            case 'Отрицательный':
                $enum = 'NEGATIVE';
                break;
            default :
                $enum = 'UNDEFINED';
        }
        return $enum;
    }

    /**
     * @param string $tonal
     * @return int
     * На основе тональности выставляет рейтинг
     */
    private function tonalToRating(string $tonal):int
    {
        switch ($tonal){
            case 'Положительный':
                $a = 10;
                break;
            case 'Нейтральный':
                $a = 8;
                break;
            case 'Отрицательный':
                $a = 6;
                break;
            default:
                $a = -1;
        }
        return $a;
    }

    /**
     * @param array $meta_info
     * Установка мета-данных
     */
    private function setMetaInfo(array $meta_info):void
    {
        $this->mainData['meta_info'] = $meta_info;
    }

    /**
     * @param array $config
     * Устанавливает конфиги для отправки в БД
     */
    private function setInnerConfig(array $config):void
    {
        $this->mainData['config'] = $config;
    }
}