<?php


namespace parsing;


class P
{
    const END_MESSAGE   = 0;
    const END_CODE      = 42;

    private $status;
    private $platform;
    private $source;

    private $getter;
    private $filter;
    private $model;



    public function __construct($platform, $source)
    {
        $this->platform = $platform;
        $this->source = $source;
    }

    public function parseSource()
    {
        while ($this->status != self::END_MESSAGE){
            $buffer = $this->getter->getNextReviews();

            if ($buffer === self::END_CODE) {
                $this->status = self::END_MESSAGE;
                continue;
            }

            $buffer = $this->filter->clearData($buffer);
            $this->model->writeData($buffer);
        }
    }
}