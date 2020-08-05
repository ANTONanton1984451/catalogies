<?php


namespace parsing\platforms\google;


use parsing\factories\factory_interfaces\ModelInterface;

class GoogleModel implements ModelInterface
{
    public function writeData($reviews)
    {
        var_dump($reviews);
    }
}