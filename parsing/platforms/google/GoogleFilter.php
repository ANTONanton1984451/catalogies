<?php


namespace parsing\platforms\google;


use parsing\factories\factory_interfaces\FilterInterface;
use parsing\platforms\Filter;

/**
 * Class GoogleFilter
 * @package parsing\platforms\google
 */
class GoogleFilter implements FilterInterface
{

  private $buffer_info;
  private $buffer_info_temp;

//ToDo::добавить поле имени пользователя в таблицу review
//Todo:поменять название в таблицу и соответственно метод в классе DB
    /**
     * @param array $data-данные,поступающие из буфера для обработки
     * @return array
     * Метод преобразует данные в вид,приемлемый для записи в БД.
     * Не изменяет конфиги,а просто передаёт их дальше в буфер!!!!!!!!
     * Выдаёт конфиги,отзывы и метаданные для данного $source
     */
  public function clearData($data):array
  {

       $this->buffer_info      = [];
       $this->buffer_info_temp = [];

       $this->buffer_info_temp = $data;

       $this->setMetaInfo();
       $this->formReview();

       $this->buffer_info['config'] = $this->buffer_info_temp['config'];

       return $this->buffer_info;
  }
  public function setConfig($config)
  {
      // TODO: Implement setConfig() method.
  }

    /**
     * Перенос мета-информации из буферного хранилища в основное
     */
  private function setMetaInfo():void
  {
      $this->buffer_info['meta_info'] = json_encode([
                                          'total_rating'=>$this->buffer_info_temp['platform_info']['averageRating'],
                                          'review_count'=>$this->buffer_info_temp['platform_info']['totalReviewCount']
                                                    ]);
  }

    /**
     * Формирует отзывы к нормальному виду
     */
  private function formReview():void
  {
      foreach ($this->buffer_info_temp['platform_info']['reviews'] as $v){

          $ratingInt                 = $this->enumToInt($v['starRating']);

          $oneReview['identifier']   = json_encode(['identifier'=>$v['reviewId'],'name'=>$v['reviewer']['displayName']]);
          $oneReview['rating']       = $ratingInt;
          $oneReview['date']         = strtotime($v['updateTime']);
          $oneReview['tonal']        = $this->intToTonal($ratingInt);

          if(isset($v['comment'])){
              $oneReview['text'] = $this->splitText($v['comment']);
          }else{
              $oneReview['text'] = '';
          }

          $this->buffer_info['reviews'][] = $oneReview;
      }

  }

    /**
     * @param string $rating
     * @return int
     * Переводит Гугловский енам в числовой эквивалент
     */
  private function enumToInt(string $rating):int
  {
      switch ($rating){
          case 'FIVE':
              return 5;
              break;
          case 'FOUR':
              return 4;
              break;
          case 'THREE':
              return 3;
              break;
          case 'TWO':
              return 2;
              break;
          case 'ONE':
              return 1;
              break;
          default:
              return -1;
      }
  }


    /**
     * @param int $rating
     * @return string
     * Основываясь на диапозоне оценок,переводит числовой параметр оценки в тональность
     */
  private function intToTonal(int $rating):string
  {
      if($rating > 0 && $rating < 4 ){
          return 'NEGATIVE';
      }
      if($rating === 4){
          return 'NEUTRAL';
      }
      if($rating === 5){
          return 'POSITIVE';
      }
      return 'UNDEFINED';
  }


    /**
     * @param string $text
     * @return string|null
     * т.к. Большинство отзывов имеют приписку перевода на английский,который не нужен,
     * и данный метод обрезает эту приписку
     */
  private function splitText(string $text):?string
  {
        $text_arr = explode('(Translated by Google)',$text);
        $text = $text_arr[0];
        return trim($text);
  }

}