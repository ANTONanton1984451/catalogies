<?php

namespace parsing\platforms;

abstract class Getter
{
    protected $source;
    protected $actual;
    protected $track;

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

    public abstract function getNextReview();
    public abstract function getAllReview();
    public abstract function getNotifications();
}