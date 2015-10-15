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

        $log->addError('charge failed', ['client_id' => 123]);
    }

    public function testError()
    {
        $error = $this->notifier->notice['errors'][0];
        $this->assertEquals('billing.ERROR', $error['type']);
        $this->assertEquals('charge failed', $error['message']);
    }

    public function testBacktrace()
    {
        $backtrace = $this->notifier->notice['errors'][0]['backtrace'];
        $wanted = [[
          'file' => __FILE__,
          'line' => 19,
          'function' => 'Airbrake\Tests\MonologHandlerTest->setUp',
        ], [
          'file' => dirname(dirname(__FILE__)).'/vendor/phpunit/phpunit/src/Framework/TestCase.php',
          'line' => 764,
          'function' => 'PHPUnit_Framework_TestCase->runBare',
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
