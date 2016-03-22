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

    public function __construct($message, $trace = [])
    {
        $this->message = $message;
        $frame = array_shift($trace);
        if ($frame != null) {
            if (isset($frame['file'])) {
                $this->file = $frame['file'];
            }
            if (isset($frame['line'])) {
                $this->line = $frame['line'];
            }
        }
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
