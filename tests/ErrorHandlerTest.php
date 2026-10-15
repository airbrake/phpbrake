<?php

namespace Airbrake\Tests;

use PHPUnit\Framework\TestCase;

class ErrorHandlerTest extends TestCase
{
    use ChecksForError;
    use ChecksForException;

    public static function exceptionProvider()
    {
        $notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);
        $handler = new \Airbrake\ErrorHandler($notifier);
        $handler->register();
        $_SERVER['HTTP_HOST'] = 'airbrake.io';
        $_SERVER['REQUEST_URI'] = '/hello';

        $handler->onException(Troublemaker::newException());

        return [[$notifier]];
    }

    public static function undefinedVarErrorProvider()
    {
        return [
            [self::arrangeOnErrorNotifier(), 'OnError'],
        ];
    }

    public function testSuppression()
    {
        list($notifier, $handler) = self::makeHandlerBoundNotifier();

        @Troublemaker::echoUndefinedIndex();
        $handler->onShutdown();

        $this->assertNull($notifier->notice);
    }

    private static function makeHandlerBoundNotifier()
    {
        $notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);
        $handler = new \Airbrake\ErrorHandler($notifier);
        $handler->register();

        return [$notifier, $handler];
    }

    private static function arrangeOnErrorNotifier()
    {
        $saved = error_reporting(E_ALL | E_STRICT);
        list($notifier) = self::makeHandlerBoundNotifier();
        Troublemaker::echoUndefinedVar();

        return $notifier;
    }

    private function setErrorReportingLevel() {
      error_reporting(E_ALL | E_STRICT);
    }
}
