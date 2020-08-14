<?php

namespace parsing\factories\factory_interfaces;

interface FilterInterface
{
    public function clearData($raw_data);
    public function setConfig($config);
}