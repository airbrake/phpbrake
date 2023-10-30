<?php

namespace Airbrake\Tests;

use Airbrake;

use PHPUnit\Framework\TestCase;

class NotifierTest extends TestCase
{
    use ChecksForException;

    public static function newNotifier()
    {
        return new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);
    }

    public static function exceptionProvider()
    {
        $notifier = self::newNotifier();
        $_SERVER['HTTP_HOST'] = 'airbrake.io';
        $_SERVER['REQUEST_URI'] = '/hello';
        $notifier->notify(Troublemaker::newException());

        return [[$notifier]];
    }

    public function testNotify()
    {
        $notifier = self::newNotifier();
        $resp = $notifier->notify(Troublemaker::newException());
        $this->assertEquals('12345', $resp['id']);
    }

    public function testNotifyAsync()
    {
        $notifier = self::newNotifier();
        $promise = $notifier->notifyAsync(Troublemaker::newException());
        $notice = null;
        $promise->then(function ($r) use (&$notice) {
            $notice = $r;
        });
        $promise->wait();
        $this->assertEquals('12345', $notice['id']);
    }

    /**
     * @dataProvider noticesHostExamples
     */
    public function testUrlWithCustomHost($opt, $expectedUrl, $comment)
    {
        $notifier = new NotifierMock($opt);
        $notifier->notify(Troublemaker::newException());
        $this->assertEquals($expectedUrl, $notifier->url, $comment);
    }

    public static function noticesHostExamples()
    {
        $defaultOpt = [
            'projectId' => 42,
            'projectKey' => 'api_key',
        ];
        return [[
            $defaultOpt,
            'https://api.airbrake.io/api/v3/projects/42/notices',
            'No host given'
        ], [
            array_merge($defaultOpt, ['host' => 'errbit.example.com']),
            'https://errbit.example.com/api/v3/projects/42/notices',
            'Custom host without scheme'
        ], [
            array_merge($defaultOpt, ['host' => 'http://errbit.example.com']),
            'http://errbit.example.com/api/v3/projects/42/notices',
            'Custom host with scheme'
        ], [
            array_merge($defaultOpt, ['host' => 'ftp://errbit.example.com']),
            'https://ftp//errbit.example.com/api/v3/projects/42/notices',
            'Custom host with wrong scheme'
        ]];
    }

    public function testEmptyOptions()
    {
        $this->expectException(Airbrake\Exception::class);
        new NotifierMock([]);
    }

    public function testNoProjectKey()
    {
        $this->expectException(Airbrake\Exception::class);
        new NotifierMock(['projectId' => 42]);
    }

    public function testNoProjectId()
    {
        $this->expectException(Airbrake\Exception::class);
        new NotifierMock(['projectKey' => 'some-key']);
    }

    public function testRootDirectoryOption()
    {
        $rootDir = dirname(__DIR__);
        $notifier = new NotifierMock([
            'projectId' => 42,
            'projectKey' => 'api_key',
            'rootDirectory' => $rootDir,
        ]);
        $notifier->notify(Troublemaker::newException());
        $this->assertEquals(
            $rootDir,
            $notifier->notice['context']['rootDirectory'],
            'The rootDirectory option is sent in the context'
        );
        $this->assertEquals(
            '/PROJECT_ROOT/tests/Troublemaker.php',
            $notifier->notice['errors'][0]['backtrace'][0]['file'],
            'The root dir is filtered from the backtrace'
        );
        $this->assertArrayHasKey('revision', $notifier->notice['context']);
    }

    public function testRemoteConfigOptionDefaut()
    {
        $notifier = new NotifierMock([
            'projectId' => 42,
            'projectKey' => 'api_key'
        ]);
        $notifier->setupRemoteConfigMock();
        $notifier->remoteConfigMock->mockErrorConfig('remote.airbrake.io');
        $notifier->notify(Troublemaker::newException());
        $this->assertEquals(
            $notifier->url,
            "https://remote.airbrake.io/api/v3/projects/42/notices",
            'The notice url is from the remote config'
        );
    }

    public function testRemoteConfigOptionDisabled()
    {
        $notifier = new NotifierMock([
            'projectId' => 42,
            'projectKey' => 'api_key',
            'remoteConfig' => false,
        ]);
        $notifier->notify(Troublemaker::newException());
        $this->assertEquals(
            $notifier->url,
            "https://api.airbrake.io/api/v3/projects/42/notices",
            'The notice url is not from the remote config'
        );
    }

    /** @dataProvider errorResponseProvider */
    public function testErrorResponse($notice, $expectedErrorMessage)
    {
        $this->assertEquals($expectedErrorMessage, $notice['error']);
    }

    public static function errorResponseProvider()
    {
        $notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);

        $notifier->resp = new ResponseMock(400, '{"message":"dummy error"}');
        $notice400 = $notifier->notify(Troublemaker::newException());

        $notifier->resp = new ResponseMock(500, '<html>500 Internal Server Error</html>');
        $notice500 = $notifier->notify(Troublemaker::newException());

        return [
            [$notice400, 'dummy error'],
            [$notice500, '<html>500 Internal Server Error</html>'],
        ];
    }

    public function testContextUserAddr()
    {
        $notifier = self::newNotifier();
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $notifier->notify(Troublemaker::newException());

        $this->assertEquals(
            '127.0.0.1',
            $notifier->notice['context']['userAddr']
        );
    }

    public function testContextUserAddrXForwardedFor()
    {
        $notifier = self::newNotifier();
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 2.2.2.2';
        $notifier->notify(Troublemaker::newException());

        $this->assertEquals(
            '2.2.2.2',
            $notifier->notice['context']['userAddr']
        );
    }

    public function testDeprecatedKeysBlacklist()
    {
        $capture = tmpfile();
        $saved = ini_set('error_log', stream_get_meta_data($capture)['uri']);

        $notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
            'keysBlacklist' => ['/key1/'],
        ]);

        ini_set('error_log', $saved);
        $errorLog = stream_get_contents($capture);

        $deprecationWarning = '/keysBlacklist is a deprecated option/';

        $this->assertMatchesRegularExpression($deprecationWarning, $errorLog);

        $notice = $notifier->buildNotice(Troublemaker::newException());
        $notice['params'] = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => ['key1' => 'value1'],
        ];
        $resp = $notifier->sendNotice($notice);

        $this->assertEquals('12345', $resp['id']);
        $this->assertEquals([
            'key1' => '[Filtered]',
            'key2' => 'value2',
            'key3' => ['key1' => '[Filtered]'],
        ], $notifier->notice['params']);
    }

    public function testKeysBlocklist()
    {
        $notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
            'keysBlocklist' => ['/key1/'],
        ]);
        $notice = $notifier->buildNotice(Troublemaker::newException());
        $notice['params'] = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => ['key1' => 'value1'],
        ];
        $resp = $notifier->sendNotice($notice);

        $this->assertEquals('12345', $resp['id']);
        $this->assertEquals([
            'key1' => '[Filtered]',
            'key2' => 'value2',
            'key3' => ['key1' => '[Filtered]'],
        ], $notifier->notice['params']);
    }

    public function testNestedException()
    {
        $notifier = self::newNotifier();
        $exc = Troublemaker::newNestedException();
        $notifier->notify($exc);

        $this->assertCount(2, $notifier->notice['errors']);

        $err = $notifier->notice['errors'][0];
        $this->assertEquals($err['message'], 'world');
        $this->assertEquals($err['type'], 'Exception');
        $this->assertEquals(
            $err['backtrace'][0]['function'],
            "Airbrake\Tests\Troublemaker::newNestedException"
        );
        $this->assertEquals(
            $err['backtrace'][0]['file'],
            dirname(dirname(__FILE__))."/tests/Troublemaker.php"
        );
        $this->assertEquals($err['backtrace'][0]['line'], 42);

        $err = $notifier->notice['errors'][1];
        $this->assertEquals($err['message'], 'hello');
        $this->assertEquals($err['type'], 'Exception');
        $this->assertEquals(
            $err['backtrace'][0]['function'],
            "Airbrake\Tests\Troublemaker::doNewException"
        );
        $this->assertEquals(
            $err['backtrace'][0]['file'],
            dirname(dirname(__FILE__))."/tests/Troublemaker.php"
        );
        $this->assertEquals($err['backtrace'][0]['line'], 19);
    }

    public function testPhpModulesAreSkipped()
    {
        $notifier = self::newNotifier();
        $exc = new FakeTraceException();
        $exc->addFakeTrace(2, [
            'file' => 'newrelic/Guzzle6',
            'line' => 1,
            'function' => 'GuzzleHttp\Handler\{closure}',
            'class' => 'GuzzleHttp\Handler\Proxy',
            'args' => [],
        ]);
        $notifier->notify($exc);

        $this->assertEquals(
            $notifier->notice['errors'][0]['backtrace'][1]['file'],
            'newrelic/Guzzle6'
        );
    }
}
