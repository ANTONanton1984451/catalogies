<?php
// todo: Перенести готовые парсеры из предыдущей архитектуры, в директорию platforms и разбить на модули
// todo: Проверка на соответствие сообщения необходимой тональности
// todo: Если найдена искомая тональность, добавить данный отзыв в массив $notifies
// todo: В зависимости от модели прописать логику сохранения отзыва в БД
// todo: Запись мета-данных в БД
// todo: Сделать при помощи контейнера зависимостей вызов конструкторов getter, filter and model


namespace parsing;

use Pimple\Container;

class Parser
{
    const STATUS_ACTIVE     = 0;
    const STATUS_COMPLETE   = 1;
    const STATUS_STOPPED    = 2;

    const END_MESSAGE   = 'end';
    const END_CODE      = 42;

    /**
     * Массив с отзывами, которые ищет пользователь сервиса, и о которых его стоит уведомить
     * @var array
     */
    private $notifies = [];

    /**
     * Статус работы парсера
     * @var
     */
    private $status;

    private $getter;
    private $filter;

    /**
     * Модель для работы с ORM и отправкой каждого конкретного отзыва в БД
     * @var
     */
    private $model;

    public function __construct($platform, $source)
    {

    }

    public function generateJsonMessage(){
        $message = json_encode($this->notifies);
        return $message;
    }

    public function parseSource() {
        while ($this->status != self::END_MESSAGE){

            $buffer = $this->getter->getNextReview();

            if ($buffer == self::END_CODE) {
                $this->status = self::END_MESSAGE;
                continue;
            }

            $buffer = $this->filter->clearData($buffer);
        }
    }
}