<?php

namespace Kavenegar\Exceptions;

use RuntimeException;

class BaseRuntimeException extends RuntimeException
{
    public function __construct($message, $code = 0)
    {
        parent::__construct($message, $code);
    }

    public function __toString()
    {
        return $this->errorMessage();
    }

    final public function errorMessage()
    {
        return "\r\n" . $this->getName() . "[{$this->code}] : {$this->message}\r\n";
    }

    public function getName()
    {
        return 'BaseRuntimeException';
    }
}
