<?php

namespace parsing\factories\factory_interfaces;

interface GetterInterface
{
    const END_CODE = 42;

    const STATUS_HANDLED  = 'HANDLED';
    const STATUS_NEW = 'NEW';
    const STATUS_UNPROCESSABLE = 'UNPROCESSABLE';

    public function setConfig($config);
    public function getNextRecords();
}