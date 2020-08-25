<?php

namespace parsing\factories\factory_interfaces;

interface GetterInterface
{
    const END_CODE = 42;

    const HANDLED_TRUE  = 'HANDLED';
    const HANDLED_FALSE = 'NEW';

    public function setConfig($config);
    public function getNextRecords();
}