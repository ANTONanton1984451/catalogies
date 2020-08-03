<?php

namespace parsing\platforms;

abstract class Getter
{
    protected $source;
    protected $track;
    protected $handled;

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

    public abstract function getNextReviews();
}