<?php


namespace parsing\platforms\google;


use parsing\factories\factory_interfaces\FilterInterface;


/**
 * Class GoogleFilter
 * @package parsing\platforms\google
 */
class GoogleFilter implements FilterInterface
{
  private $buffer_info;
  private $buffer_info_temp;


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
       $this->buffer_info_temp = $data;

       if(empty($data['platform_info']['reviews'])){

          $this->buffer_info['meta'] = [
                                        'rating'=>     $data['platform_info']['averageRating'],
                                        'reviewCount'=>     $data['platform_info']['totalReviewCount']
                                       ];

       }else{

           $this->formReview();
           $this->buffer_info['config'] = $this->buffer_info_temp['config'];

       }

       return $this->buffer_info;
  }

    /**
     * Формирует отзывы к нормальному виду
     * На самом деле фильтр берёт на себя немного функции Модели,что не очень хорошо,но пока всё работает отлично
     * поэтому в ближайшее время менять тут ничего не стоит
     * todo:Тудушка стоит для того,чтобы когда-то в будущем я это исправил
     */
  private function formReview():void
  {
      foreach ($this->buffer_info_temp['platform_info']['reviews'] as $v){

          $ratingInt                 = $this->enumToInt($v['starRating']);

          $review['identifier']   = json_encode(['identifier'=>$v['reviewId'],'name'=>$v['reviewer']['displayName']]);
          $review['rating']       = $ratingInt;
          $review['date']         = strtotime($v['updateTime']);
          $review['tonal']        = $this->ratingToTonal($ratingInt);
          if(isset($v['comment'])){
              $review['text'] = $this->splitText($v['comment']);
          }else{
              $review['text'] = '';
          }
          if(isset($v['reviewReply'])){
              $review['is_answered'] = 'true';
          }else{
              $review['is_answered'] = 'false';
          }

          $this->buffer_info['reviews'][] = $review;
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
          case 'FOUR':
              return 4;
          case 'THREE':
              return 3;
          case 'TWO':
              return 2;
          case 'ONE':
              return 1;
          default:
              return -1;
      }
  }


    /**
     * @param int $rating
     * @return string
     * Основываясь на диапозоне оценок,переводит числовой параметр оценки в тональность
     */
  private function ratingToTonal(int $rating):string
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