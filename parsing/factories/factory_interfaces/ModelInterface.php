<?php

namespace parsing\factories\factory_interfaces;

interface ModelInterface
{
    const STATUS_HANDLED  = 'HANDLED';
    const STATUS_NEW = 'NEW';
    const STATUS_UNPROCESSABLE = 'UNPROCESSABLE';

    public function setConfig($config);
    public function writeData($records);
}