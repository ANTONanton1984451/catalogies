<?php

namespace parsing\factories\factory_interfaces;

interface ModelInterface
{
    public function setSourceHash($source_hash);
    public function writeData($reviews);
}