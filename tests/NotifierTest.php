<?php

namespace Airbrake\Tests;

use PHPUnit_Framework_TestCase;

class NotifierTest extends PHPUnit_Framework_TestCase
{
    use ChecksForException;

    public function exceptionProvider()
    {
        $notifier = new NotifierMock([
        'projectId' => 1,
        'projectKey' => 'api_key',
        ]);
        $_SERVER['HTTP_HOST'] = 'airbrake.io';
        $_SERVER['REQUEST_URI'] = '/hello';
        $notifier->notify(Troublemaker::newException());

        return [[$notifier]];
    }

    public function testRespId()
    {
        $notifier = new NotifierMock([
        'projectId' => 1,
        'projectKey' => 'api_key',
        ]);
        $resp = $notifier->notify(Troublemaker::newException());
        $this->assertEquals('12345', $resp['id']);
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

    public function noticesHostExamples()
    {
        $defaultOpt = [
        'projectId' => 42,
        'projectKey' => 'api_key',
        ];
        return [
        [
        $defaultOpt,
        'https://api.airbrake.io/api/v3/projects/42/notices?key=api_key',
        'No host given'
        ],
        [
        array_merge($defaultOpt, ['host' => 'errbit.example.com']),
        'https://errbit.example.com/api/v3/projects/42/notices?key=api_key',
        'Custom host without scheme'
        ],
        [
        array_merge($defaultOpt, ['host' => 'http://errbit.example.com']),
        'http://errbit.example.com/api/v3/projects/42/notices?key=api_key',
        'Custom host with scheme'
        ],
        [
        array_merge($defaultOpt, ['host' => 'ftp://errbit.example.com']),
        'https://ftp://errbit.example.com/api/v3/projects/42/notices?key=api_key',
        'Custom host with wrong scheme'
        ],
        ];
    }

  /**
   * @expectedException \Airbrake\Exception
   */
    public function testEmptyOptions()
    {
        new NotifierMock([]);
    }

  /**
   * @expectedException \Airbrake\Exception
   */
    public function testNoProjectKey()
    {
        new NotifierMock(['projectId' => 42]);
    }

  /**
   * @expectedException \Airbrake\Exception
   */
    public function testNoProjectId()
    {
        new NotifierMock(['projectKey' => 'some-key']);
    }

    public function testRootDirectoryOption()
    {
        $notifier = new NotifierMock([
        'projectId' => 42,
        'projectKey' => 'api_key',
        'rootDirectory' => __DIR__,
        ]);
        $notifier->notify(Troublemaker::newException());
        $this->assertEquals(
            __DIR__,
            $notifier->notice['context']['rootDirectory'],
            'The rootDirectory option is sent in the context'
        );
        $this->assertEquals(
            '[PROJECT_ROOT]/Troublemaker.php',
            $notifier->notice['errors'][0]['backtrace'][0]['file'],
            'The root dir is filtered from the backtrace'
        );
    }

  /** @dataProvider errorResponseProvider */
    public function testErrorResponse($notice, $expectedErrorMessage)
    {
        $this->assertEquals($expectedErrorMessage, $notice['error']);
    }

    public function errorResponseProvider()
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
}
