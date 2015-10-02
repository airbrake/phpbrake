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
}
