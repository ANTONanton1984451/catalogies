<?php

namespace parsing\platforms;

abstract class Getter
{
    const END_CODE = 42;

    protected $source;
    protected $track;
    protected $handle;
}