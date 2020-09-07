<?php

namespace parsing\factories\factory_interfaces;

interface GetterInterface extends ConstantInterfaces {
    public function setConfig($config);
    public function getNextRecords();
}