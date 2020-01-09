<?php

namespace Airbrake\Tests;

/**
 * We opt for composition and magic method proxying of Exceptions instead of
 * simply extending \Exception because getTrace() is marked as final.
 */
class FakeTraceException
{
    protected $exc;
    protected $traceOverrides;

    public function __construct()
    {
        $this->exc = new \Exception('wrapped exception', 1);
        $this->traceOverrides = [];
    }

    public function getTrace()
    {
        return $this->traceOverrides + $this->exc->getTrace();
    }

    public function addFakeTrace($position, $frame)
    {
        $this->traceOverrides[$position] = $frame;
    }

    public function __call($name, $args)
    {
        return call_user_func_array([$this->exc, $name], $args);
    }

    public function __get($name)
    {
        return $this->exc->$name;
    }
}
