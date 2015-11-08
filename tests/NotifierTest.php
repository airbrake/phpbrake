<?php

namespace Airbrake\Tests;

use PHPUnit_Framework_TestCase;

class NotifyTest extends PHPUnit_Framework_TestCase
{
    protected $notifier;
    protected $resp;

    public function setUp()
    {
        $this->notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);
        $_SERVER['HTTP_HOST'] = 'airbrake.io';
        $_SERVER['REQUEST_URI'] = '/hello';
        $this->resp = $this->notifier->notify(Troublemaker::newException());
    }

    public function testPostsToURL()
    {
        $this->assertEquals(
            'https://api.airbrake.io/api/v3/projects/1/notices?key=api_key',
            $this->notifier->url
        );
    }

    public function testPostsError()
    {
        $notice = $this->notifier->notice;
        $error = $notice['errors'][0];
        $this->assertEquals('Exception', $error['type']);
        $this->assertEquals('hello', $error['message']);
    }

    public function testPostsBacktrace()
    {
        $backtrace = $this->notifier->notice['errors'][0]['backtrace'];
        $this->assertCount(12, $backtrace);

        $wanted = [[
            'file' => dirname(dirname(__FILE__)).'/tests/Troublemaker.php',
            'line' => 19,
            'function' => 'Airbrake\Tests\Troublemaker::doNewException',
        ], [
            'file' => dirname(dirname(__FILE__)).'/tests/Troublemaker.php',
            'line' => 24,
            'function' => 'Airbrake\Tests\Troublemaker::newException',
        ]];
        for ($i = 0; $i < count($wanted); $i++) {
            $this->assertEquals($wanted[$i], $backtrace[$i]);
        }
    }

    public function testPostsNotifier()
    {
        $this->assertEquals(
            'phpbrake',
            $this->notifier->notice['context']['notifier']['name']
        );
    }

    public function testPostsURL()
    {
        $this->assertEquals(
            'http://airbrake.io/hello',
            $this->notifier->notice['context']['url']
        );
    }
}

class FilterReturnsNullTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);
        $this->notifier->addFilter(function () {
            return;
        });
        $this->notifier->notify(new \Exception('hello'));
    }

    public function testNoticeIsIgnored()
    {
        $this->assertNull($this->notifier->notice);
    }
}

class FilterReturnsFalseTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);
        $this->notifier->addFilter(function () {
            return false;
        });
        $this->notifier->notify(new \Exception('hello'));
    }

    public function testNoticeIsIgnored()
    {
        $this->assertNull($this->notifier->notice);
    }
}

class ModificationTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);
        $this->notifier->addFilter(function () {
            $notice['context']['environment'] = 'production';
            unset($notice['environment']);

            return $notice;
        });
        $this->notifier->notify(new \Exception('hello'));
    }

    public function testNoticeIsModified()
    {
        $notice = $this->notifier->notice;
        $this->assertEquals('production', $notice['context']['environment']);
    }

    public function testEnvironmentIsUnset()
    {
        $notice = $this->notifier->notice;
        $this->assertFalse(isset($notice['environment']));
    }
}

class OnErrorTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        error_reporting(E_ALL | E_STRICT);

        $this->notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);
        $handler = new \Airbrake\ErrorHandler($this->notifier);
        $handler->register();

        Troublemaker::echoUndefinedVar();
    }

    public function testPostsError()
    {
        $notice = $this->notifier->notice;
        $error = $notice['errors'][0];
        $this->assertEquals('Airbrake\Errors\Notice', $error['type']);
        $this->assertEquals('Undefined variable: undefinedVar', $error['message']);
    }

    public function testPostsBacktrace()
    {
        $backtrace = $this->notifier->notice['errors'][0]['backtrace'];
        $this->assertCount(12, $backtrace);

        $wanted = [[
            'file' => dirname(dirname(__FILE__)).'/tests/Troublemaker.php',
            'line' => 9,
            'function' => 'Airbrake\Tests\Troublemaker::doEchoUndefinedVar',
        ], [
            'file' => dirname(dirname(__FILE__)).'/tests/Troublemaker.php',
            'line' => 14,
            'function' => 'Airbrake\Tests\Troublemaker::echoUndefinedVar',
        ]];
        for ($i = 0; $i < count($wanted); $i++) {
            $this->assertEquals($wanted[$i], $backtrace[$i]);
        }
    }
}

class OnExceptionTest extends NotifyTest
{
    public function setUp()
    {
        $this->notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);
        $handler = new \Airbrake\ErrorHandler($this->notifier);
        $handler->register();

        $handler->onException(Troublemaker::newException());
        $this->resp = $this->notifier->resp;
    }
}

class OnShutdownTest extends OnErrorTest
{
    public function setUp()
    {
        $this->notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);
        $handler = new \Airbrake\ErrorHandler($this->notifier);
        $handler->register();

        @Troublemaker::echoUndefinedVar();
        $handler->onShutdown();
    }
}

class ResponseTest extends NotifyTest
{
    public function testRespId()
    {
        $this->assertEquals('12345', $this->resp['id']);
    }
}

class BadRequestResponseTest extends PHPUnit_Framework_TestCase
{
    protected $notifier;
    protected $resp;

    public function setUp()
    {
        $this->notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);
        $this->notifier->resp = [
            'headers' => 'HTTP/1.1 400 Bad Request',
            'data' => '{"error":"dummy error"}',
        ];
        $this->resp = $this->notifier->notify(Troublemaker::newException());
    }

    public function testRespError()
    {
        $this->assertEquals('dummy error', $this->resp['error']);
    }
}

class InternalServerErrorResponseTest extends PHPUnit_Framework_TestCase
{
    protected $notifier;
    protected $resp;

    public function setUp()
    {
        $this->notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);
        $this->notifier->resp = [
            'headers' => 'HTTP/1.1 500 Internal Server Error',
            'data' => '<html>500 Internal Server Error</html>',
        ];
        $this->resp = $this->notifier->notify(Troublemaker::newException());
    }

    public function testRespError()
    {
        $this->assertEquals('<html>500 Internal Server Error</html>', $this->resp['error']);
    }
}
