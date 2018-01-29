<?php

namespace Airbrake\Tests;

use PHPUnit_Framework_TestCase;

class ErrorHandlerTest extends PHPUnit_Framework_TestCase
{
    use ChecksForError;
    use ChecksForException;

    public function exceptionProvider()
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

    public function undefinedVarErrorProvider()
    {
        return [
            [$this->arrangeOnErrorNotifier(), 'OnError'],
            [$this->arrangeOnShutdownNotifier(), 'OnShutdown'],
        ];
    }

    private function makeHandlerBoundNotifier()
    {
        $notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);
        $handler = new \Airbrake\ErrorHandler($notifier);
        $handler->register();

        return [$notifier, $handler];
    }

    private function arrangeOnErrorNotifier()
    {
        $saved = error_reporting(E_ALL | E_STRICT);
        list($notifier, $handler) = $this->makeHandlerBoundNotifier();
        Troublemaker::echoUndefinedVar();
        error_reporting($saved);

        return $notifier;
    }

    private function arrangeOnShutdownNotifier()
    {
        list($notifier, $handler) = $this->makeHandlerBoundNotifier();
        @Troublemaker::echoUndefinedVar();
        $handler->onShutdown();

        return $notifier;
    }
}
