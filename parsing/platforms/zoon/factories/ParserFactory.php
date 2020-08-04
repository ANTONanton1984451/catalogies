<?php

namespace parsing\platforms\factories;

use Exception;

class ParserFactory
{
    public function getFactory($platform)
    {
        switch ($platform) {
            case 'zoon' :
                $factory = new ZoonFactory();
                break;
            case 'google' :
                $factory = new GoogleFactory();
                break;
            case 'topdealers' :
                $factory = new TopDealersFactory();
                break;
            case 'yell' :
                $factory = new YellFactory();
                break;
            default :
                throw new Exception("Неизвестный тип фабрики [{$platform}]");
        }

        return $factory;
    }
}