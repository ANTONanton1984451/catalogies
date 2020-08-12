<?php

namespace parsing\platforms\zoon;

use parsing\DbController;
use parsing\factories\factory_interfaces\ModelInterface;

class ZoonModel implements ModelInterface
{
    private $source_hash;

    public function setSourceHash($source_hash) {
        $this->source_hash = $source_hash;
    }

    public function writeData($reviews) {
        $database = new DbController();
        $database->insertReviews($reviews, $this->source_hash);
    }


}