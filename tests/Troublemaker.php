<?php

namespace Airbrake\Tests;

class Troublemaker
{
    private static function doEchoUndefinedVar()
    {
        echo $undefinedVar;
    }

    public static function echoUndefinedVar()
    {
        self::doEchoUndefinedVar();
    }

    private static function doNewException()
    {
        return new \Exception('hello');
    }

    public static function newException()
    {
        return self::doNewException();
    }

    private static function doLogAddError($log)
    {
        $log->error('charge failed', ['client_id' => 123]);
    }

    public static function logAddError($log)
    {
        self::doLogAddError($log);
    }

    public static function newNestedException()
    {
        try {
            throw Troublemaker::newException();
        } catch (\Exception $e) {
            return new \Exception('world', 207, $e);
        }
    }
}
