<?php


namespace parsing\factories\parsers_factory;


use parsing\factories\factory_interfaces\FilterInterface;
use parsing\factories\factory_interfaces\GetterInterface;
use parsing\factories\factory_interfaces\ModelInterface;
use parsing\factories\factory_interfaces\ParserFactoryInterfaces;
use parsing\platforms\topdealers\TopDealersFilter;
use parsing\platforms\topdealers\TopDealersGetter;
use parsing\platforms\topdealers\TopDealersModel;

class TopDealersFactory implements ParserFactoryInterfaces
{
    public function buildModel(): ModelInterface
    {
        return new TopDealersModel();
    }
    public function buildGetter(): GetterInterface
    {
        return new TopDealersGetter();
    }
    public function buildFilter(): FilterInterface
    {
        return new TopDealersFilter();
    }

}