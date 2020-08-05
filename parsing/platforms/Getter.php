<?php

namespace parsing\platforms;

abstract class Getter
{
    protected $source;
    protected $actual;
    protected $track;
    protected $config;

    public function setHandled(){

    }

    public function setSource($source)
    {
        $this->source = $source;
    }

    public function setActual($actual)
    {
        $this->actual = $actual;
    }

    public function setTrack($track)
    {
        $this->track = $track;
    }
//  ToDo::при продакшене переделать в json
    public function setConfig($config)
    {
        $this->config = $config;
    }


    public abstract function getNextReviews(string $handled);
}