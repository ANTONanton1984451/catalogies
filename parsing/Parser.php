<?php
// todo: Перенести готовые парсеры из предыдущей архитектуры, в директорию platforms и разбить на модули
// todo: Проверка на соответствие сообщения необходимой тональности
// todo: Если найдена искомая тональность, добавить данный отзыв в массив $notifies
// todo: В зависимости от модели прописать логику сохранения отзыва в БД
// todo: Запись мета-данных в БД
// todo: Сделать при помощи контейнера зависимостей вызов конструкторов getter, filter and model
// todo: Возможно необходимы сеттеры и для фильтра, учесть

namespace parsing;

class Parser
{
    const END_MESSAGE       = 0;
    const ACTIVE_MESSAGE    = 1;

    const END_CODE      = 42;

    private $status;

    private $source;
    private $handled;
    private $track;

    private $getter;
    private $filter;
    private $model;

    private $notifies = [];

    public function __construct($source, $handled, $track)
    {
        $this->status = self::ACTIVE_MESSAGE;
        $this->source = $source;
        $this->handled = $handled;
        $this->track = $track;
    }

    public function parseSource()
    {
        $this->getter->setSource($this->source);
        $this->getter->setHandled($this->handled);
        $this->getter->setTrack($this->track);

        /*
        while ($this->status != self::END_MESSAGE){
            $buffer = $this->getter->getNextReviews();

            if ($buffer === self::END_CODE) {
                $this->status = self::END_MESSAGE;
                continue;
            }

            //$buffer = $this->filter->clearData($buffer);
            $this->model->writeData($buffer);
        }*/
    }

    public function generateJsonMessage() {
        return json_encode($this->notifies);
    }

    public function setGetter($getter)  : void
    {
        $this->getter = $getter;
    }
    public function setFilter($filter)  : void
    {
        $this->filter = $filter;
    }
    public function setModel($model)    : void
    {
        $this->model = $model;
    }
}