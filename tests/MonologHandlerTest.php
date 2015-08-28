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
        $this->assertEquals($error['type'], 'billing.ERROR');
        $this->assertEquals($error['message'], 'charge failed');
    }

    public function testBacktrace()
    {
        $backtrace = $this->notifier->notice['errors'][0]['backtrace'];
        $wanted = [[
          'file' => __FILE__,
          'line' => 19,
          'function' => 'Monolog\Logger->addError',
        ]];
        for ($i = 0; $i < count($wanted); $i++) {
            $this->assertEquals($backtrace[$i], $wanted[$i]);
        }
    }

    public function testParams()
    {
        $params = $this->notifier->notice['params'];
        $this->assertEquals($params, [
            'monolog_context' => [
                'client_id' => 123,
            ],
        ]);
    }
}
