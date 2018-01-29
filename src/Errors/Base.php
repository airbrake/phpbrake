<?php

namespace Airbrake\Errors;

/**
 * Error wrapper that mimics Exception API. For internal usage.
 */
class Base
{
    private $message;
    private $trace;

    public function __construct($message, $trace = [])
    {
        $this->message = $message;
        $this->trace = $trace;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getFile()
    {
        return '';
    }

    public function getLine()
    {
        return 0;
    }

    public function getTrace()
    {
        return $this->trace;
    }
}
