<?php

namespace parsing\factories\parsers_factory;

use parsing\factories\factory_interfaces\ParserFactoryInterfaces;
use parsing\factories\factory_interfaces\FilterInterface;
use parsing\factories\factory_interfaces\GetterInterface;
use parsing\factories\factory_interfaces\ModelInterface;

use parsing\platforms\yell\YellFilter;
use parsing\platforms\yell\YellGetter;
use parsing\platforms\yell\YellModel;

class YellFactory implements ParserFactoryInterfaces
{
    public function buildGetter(): GetterInterface {
        return new YellGetter();
    }

    public function buildFilter(): FilterInterface {
        return new YellFilter();
    }

    public function buildModel(): ModelInterface {
        return new YellModel();
    }
}