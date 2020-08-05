<?php

namespace parsing\platforms;

abstract class Getter
{
    const END_CODE = 42;

    protected $source;
    protected $track;

    protected $config;



    public function setSource($source)
    {
        $this->source = $source;
    }

    public function setHandled($handled)
    {
        $this->handled = $handled;
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