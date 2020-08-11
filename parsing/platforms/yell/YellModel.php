<?php
namespace parsing\platforms\yell;

use parsing\factories\factory_interfaces\ModelInterface;

class YellModel implements ModelInterface
{
    public function writeData($reviews)
    {
        var_dump($reviews);
    }
}