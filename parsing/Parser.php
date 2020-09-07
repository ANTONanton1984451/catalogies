<?php
// todo: Добавлять notifies для каждого нового отзыва

namespace parsing;

use parsing\factories\factory_interfaces\FilterInterface;
use parsing\factories\factory_interfaces\GetterInterface;
use parsing\factories\factory_interfaces\ModelInterface;

class Parser
{
    const MESSAGE_END = 0;
    const MESSAGE_START = 1;

    const END_CODE = 42;

    private $status = self::MESSAGE_START;

    private $getter;
    private $filter;
    private $model;

    private $config;

    private $notifications = [];

    public function __construct($config) {
        $this->config = $config;
    }


    public function parseSource() {
        $this->getter->setConfig($this->config);
        $this->model->setConfig($this->config);

        while ($this->status != self::MESSAGE_END) {
            $buffer = $this->getter->getNextRecords();
            if ($buffer === self::END_CODE) {
                $this->status = self::MESSAGE_END;
                continue;
            }


            $buffer = $this->filter->clearData($buffer);
            $this->model->writeData($buffer);
        }
        $this->notifications = $this->model->getNotifications();
    }

    public function setGetter(GetterInterface $getter): void {
        $this->getter = $getter;
    }

    public function setFilter(FilterInterface $filter): void {
        $this->filter = $filter;
    }

    public function setModel(ModelInterface $model): void {
        $this->model = $model;
    }

    public function generateJsonMessage() {
        return json_encode($this->notifications);
    }
}