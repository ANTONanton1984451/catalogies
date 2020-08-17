<?php

namespace parsing\factories\factory_interfaces;

interface FilterInterface
{
    public function clearData($buffer);
    public function setConfig($config);
}