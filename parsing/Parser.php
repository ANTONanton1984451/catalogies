<?php
// todo: Перенести готовые парсеры из предыдущей архитектуры, в директорию platforms и разбить на модули
// todo: Проверка на соответствие сообщения необходимой тональности
// todo: Если найдена искомая тональность, добавить данный отзыв в массив $notifies
// todo: В зависимости от модели прописать логику сохранения отзыва в БД
// todo: Запись мета-данных в БД
// todo: Сделать при помощи контейнера зависимостей вызов конструкторов getter, filter and model
// todo: Возможно необходимы сеттеры и для фильтра, учесть

namespace parsing;

use parsing\factories\factory_interfaces\FilterInterface;
use parsing\factories\factory_interfaces\GetterInterface;
use parsing\factories\factory_interfaces\ModelInterface;

class Parser
{
    const END_MESSAGE       = 0;
    const ACTIVE_MESSAGE    = 1;

    const END_CODE      = 42;

    private $status;

    private $config;

    private $getter;
    private $filter;
    private $model;

    private $notifies = [];

    public function __construct($config)
    {
        $this->status = self::ACTIVE_MESSAGE;
        $this->config = $config;

    }

    public function parseSource()
    {
       $this->getter->setConfig($this->config);

        while ($this->status != self::END_MESSAGE){

            $buffer = $this->getter->getNextReviews();
            if ($buffer === self::END_CODE) {
                $this->status = self::END_MESSAGE;
                continue;
            }
            $buffer = $this->filter->clearData($buffer);

            var_dump($buffer);
        }
    }

    public function generateJsonMessage() {
        return json_encode($this->notifies);
    }

    public function setGetter(GetterInterface $getter)  : void
    {
        $this->getter = $getter;
    }
    public function setFilter(FilterInterface $filter)  : void
    {
        $this->filter = $filter;
    }
    public function setModel(ModelInterface $model)    : void
    {
        $this->model = $model;
    }
}