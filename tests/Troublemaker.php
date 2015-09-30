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
}
