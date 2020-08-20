<?php


namespace parsing\platforms\google;


use parsing\factories\factory_interfaces\ModelInterface;

class GoogleModel implements ModelInterface
{
    public function writeData($records)
    {
        var_dump($records);
    }
}