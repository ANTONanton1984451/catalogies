<?php

namespace parsing\factories;

use Exception;

use parsing\factories\factory_interfaces\ParserFactoryInterfaces;
use parsing\factories\parsers_factory\ZoonFactory;

class ParserFactory
{
    public function getFactory($platform) : ParserFactoryInterfaces {
        switch ($platform) {
            case 'zoon' :
                $factory = new ZoonFactory();
                break;
            case 'google' :
                $factory = new GoogleFactory();
                break;
            default :
                throw new Exception("Неизвестный тип фабрики [{$platform}]");
        }

        return $factory;
    }
}