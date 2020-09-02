<?php


namespace parsing\factories\parsers_factory;


use parsing\DB\DatabaseShell;
use parsing\factories\factory_interfaces\FilterInterface;
use parsing\factories\factory_interfaces\GetterInterface;
use parsing\factories\factory_interfaces\ModelInterface;
use parsing\factories\factory_interfaces\ParserFactoryInterfaces;
use parsing\platforms\google\GoogleFilter;
use parsing\platforms\google\GoogleGetter;
use parsing\platforms\google\GoogleModel;
use parsing\services\TaskQueueController;

class GoogleFactory implements ParserFactoryInterfaces
{
    public function buildFilter(): FilterInterface
    {
        return new GoogleFilter();
    }
    public function buildGetter(): GetterInterface
    {
        return new GoogleGetter(new \Google_Client(),new DatabaseShell());
    }

    public function buildModel(): ModelInterface
    {

        return new GoogleModel(new DatabaseShell(),new TaskQueueController());
    }

}