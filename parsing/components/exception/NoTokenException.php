<?php

// todo: Написание собственных Exception'ов

namespace parsing\components\exception;

use Exception;

class NoTokenException extends Exception {
    public function testMessage() {}
}