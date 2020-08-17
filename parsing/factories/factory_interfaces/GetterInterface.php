<?php

namespace parsing\factories\factory_interfaces;

interface GetterInterface
{
    const END_CODE = 42;

    public function setConfig($config);
    public function getNextRecords();
}