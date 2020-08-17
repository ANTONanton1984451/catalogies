<?php
namespace parsing\platforms\yell;

use parsing\factories\factory_interfaces\ModelInterface;

class YellModel implements ModelInterface
{
    private $sourceInfo;

    public function writeData($reviews)
    {
        var_dump($reviews);
    }

    public function setConfig($config)
    {
        $this->sourceInfo = $config;
    }
}