<?php

namespace parsing\factories\factory_interfaces;

interface GetterInterface extends ConstantInterfaces
{

    const END_CODE = 42;

    const SOURCE_HANDLED  = 'HANDLED';
    const SOURCE_NEW = 'NEW';
    const SOURCE_UNPROCESSABLE = 'UNPROCESSABLE';
    public function setConfig($config);
    public function getNextRecords();
}