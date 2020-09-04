<?php


namespace parsing\platforms\topdealers;


use parsing\factories\factory_interfaces\FilterInterface;

class TopDealersFilter implements FilterInterface
{

    private $mainData = [];

    /**
     * @param $raw_data
     * @return array
     * Преобразовывает данные в приемлемый вид
     */
    public function clearData($raw_data)
    {
        if(!empty($raw_data['reviews'])){
            $this->formReview($raw_data['reviews']);
        }

       $this->setMetaInfo($raw_data['meta_info']);
       $this->setInnerConfig($raw_data['config']);

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
     * Переводит тональность в ENUM виду в таблице
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

    /**
     * @param array $meta_info
     * Установка мета-данных
     */
    private function setMetaInfo(array $meta_info):void
    {
        $this->mainData['meta_info'] = json_encode($meta_info);
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