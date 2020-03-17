<?php

namespace Airbrake\Tests;

use PHPUnit_Framework_TestCase;

class MonologBacktraceTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);

        $log = new \Monolog\Logger('billing');
        $log->pushHandler(new \Airbrake\MonologHandler($this->notifier));

        $log->error('charge failed', [
            'client_id' => 123,
            'exception'=> Troublemaker::newException(),
        ]);
    }

    public function testBacktrace()
    {
        $notice = $this->notifier->notice;
        $context = $notice['params']['monolog_context'];

        $backtrace = $this->notifier->notice['errors'][0]['backtrace'];
        $wanted = [[
            'line' => 19,
            'file' => dirname(__FILE__).'/Troublemaker.php',
            'function' => 'Airbrake\Tests\Troublemaker::doNewException',

            'code' => [
                17 => '    private static function doNewException()',
                18 => '    {',
                19 => "        return new \Exception('hello');",
                20 => '    }',
                21 => ''
            ],
        ], [
            'file' => dirname(__FILE__).'/Troublemaker.php',
            'line' => 24,
            'function' => 'Airbrake\Tests\Troublemaker::newException',
            'code' => [
                22 => '    public static function newException()',
                23 => '    {',
                24 => '        return self::doNewException();',
                25 => '    }',
                26 => ''
            ],

        ]];
        for ($i = 0; $i < count($wanted); $i++) {
            $this->assertEquals($wanted[$i], $backtrace[$i]);
        }
    }
}
