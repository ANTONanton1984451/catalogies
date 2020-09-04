<?php


namespace parsing\factories\parsers_factory;

use parsing\factories\factory_interfaces\ParserFactoryInterfaces;
use parsing\factories\factory_interfaces\FilterInterface;
use parsing\factories\factory_interfaces\GetterInterface;
use parsing\factories\factory_interfaces\ModelInterface;

use parsing\platforms\flamp\FlampFilter;
use parsing\platforms\flamp\FlampGetter;
use parsing\platforms\flamp\FlampModel;

class FlampFactory implements ParserFactoryInterfaces
{
    public function buildGetter(): GetterInterface
    {
        return new FlampGetter();
    }

    public function buildFilter(): FilterInterface {
        return new FlampFilter();
    }

    public function buildModel(): ModelInterface {
        return new FlampModel();
    }
}