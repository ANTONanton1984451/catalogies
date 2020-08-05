<?php

namespace parsing\factories\factory_interfaces;

interface ParserFactoryInterfaces
{
    public function buildGetter()   : GetterInterface;
    public function buildFilter()   : FilterInterface;
    public function buildModel()    : ModelInterface;
}