<?php

namespace parsing\factories;

use Exception;

use parsing\factories\factory_interfaces\ParserFactoryInterfaces;

use parsing\factories\parsers_factory\YellFactory;
use parsing\factories\parsers_factory\FlampFactory;
use parsing\factories\parsers_factory\TopDealersFactory;
use parsing\factories\parsers_factory\ZoonFactory;
use parsing\factories\parsers_factory\GoogleFactory;

class ParserFactory
{
    public function getFactory($platform): ParserFactoryInterfaces
    {
        switch ($platform) {
            case 'zoon' :
                $factory = new ZoonFactory();
                break;

            case 'google' :
                $factory = new GoogleFactory();
                break;

            case 'yell';
                $factory = new YellFactory();
                break;

            case 'topdealers' :
                $factory = new TopDealersFactory();
                break;

            case 'flamp':
                $factory = new FlampFactory();
                break;

            default :
                throw new Exception("Неизвестный тип фабрики [{$platform}]");
        }

        return $factory;
    }
}