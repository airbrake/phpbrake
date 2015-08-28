<?php

namespace Airbrake\Errors;

/**
 * Error wrapper that mimics Exception API. For internal usage.
 */
class Base
{
    private $message;
    private $file;
    private $line;
    private $trace;

    public function __construct($message, $file, $line, $trace = [])
    {
        $this->message = $message;
        $this->file = $file;
        $this->line = $line;
        $this->trace = $trace;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function getLine()
    {
        return $this->line;
    }

    public function getTrace()
    {
        return $this->trace;
    }
}
