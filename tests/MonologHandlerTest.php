<?php

namespace Airbrake\Tests;

use PHPUnit_Framework_TestCase;

class MonologHandlerTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);

        $log = new \Monolog\Logger('billing');
        $log->pushHandler(new \Airbrake\MonologHandler($this->notifier));

        Troublemaker::logAddError($log);
    }

    public function testError()
    {
        $error = $this->notifier->notice['errors'][0];
        $this->assertEquals('billing.ERROR', $error['type']);
        $this->assertEquals('charge failed', $error['message']);
    }

    public function testSeverity()
    {
        $this->assertEquals('ERROR', $this->notifier->notice['context']['severity']);
    }

    public function testBacktrace()
    {
        $backtrace = $this->notifier->notice['errors'][0]['backtrace'];
        $wanted = [[
            'file' => dirname(__FILE__).'/Troublemaker.php',
            'line' => 29,
            'function' => 'Airbrake\Tests\Troublemaker::doLogAddError',
            'code' => [
                27 => '    private static function doLogAddError($log)',
                28 => '    {',
                29 => "        \$log->error('charge failed', ['client_id' => 123]);",
                30 => '    }',
                31 => '',
            ],
        ], [
            'file' => dirname(__FILE__).'/Troublemaker.php',
            'line' => 34,
            'function' => 'Airbrake\Tests\Troublemaker::logAddError',
            'code' => [
                32 => '    public static function logAddError($log)',
                33 => '    {',
                34 => '        self::doLogAddError($log);',
                35 => '    }',
                36 => '',
            ],
        ]];
        for ($i = 0; $i < count($wanted); $i++) {
            $this->assertEquals($wanted[$i], $backtrace[$i]);
        }
    }

    public function testParams()
    {
        $params = $this->notifier->notice['params'];
        $this->assertEquals([
            'monolog_context' => [
                'client_id' => 123,
            ],
        ], $params);
    }
}
