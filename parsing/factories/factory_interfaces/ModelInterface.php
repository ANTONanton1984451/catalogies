<?php

namespace parsing\factories\factory_interfaces;

interface ModelInterface extends ConstantInterfaces
{
    public function setConfig($config);
    public function writeData($records);
    public function getNotifications() : array;
}