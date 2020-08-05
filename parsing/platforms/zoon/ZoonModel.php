<?php

namespace parsing\platforms\zoon;

use parsing\factories\factory_interfaces\ModelInterface;

class ZoonModel implements ModelInterface
{
    public function writeData($reviews)
    {
        var_dump($reviews);
    }
}