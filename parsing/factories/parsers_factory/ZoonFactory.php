<?php

namespace parsing\factories\parsers_factory;

use parsing\factories\factory_interfaces\FilterInterface;
use parsing\factories\factory_interfaces\GetterInterface;
use parsing\factories\factory_interfaces\ModelInterface;
use parsing\factories\factory_interfaces\ParserFactoryInterfaces;

use parsing\platforms\zoon\ZoonFilter;
use parsing\platforms\zoon\ZoonGetter;
use parsing\platforms\zoon\ZoonModel;

class ZoonFactory implements ParserFactoryInterfaces
{

    public function buildGetter(): GetterInterface
    {
        return new ZoonGetter();
    }

    public function buildFilter(): FilterInterface
    {
        return new ZoonFilter();
    }

    public function buildModel(): ModelInterface
    {
        return new ZoonModel();
    }
}