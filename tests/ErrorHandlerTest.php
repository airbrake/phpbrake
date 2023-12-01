<?php

namespace Airbrake\Tests;

use PHPUnit\Framework\TestCase;

class ErrorHandlerTest extends TestCase
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
        $this->setErrorReportingLevel();
        list($notifier, $handler) = $this->makeHandlerBoundNotifier();
        Troublemaker::echoUndefinedVar();

        return $notifier;
    }

    private function arrangeOnShutdownNotifier()
    {
        $this->setErrorReportingLevel();
        list($notifier, $handler) = $this->makeHandlerBoundNotifier();
        @Troublemaker::echoUndefinedVar();
        $handler->onShutdown();

        return $notifier;
    }

    private function setErrorReportingLevel() {
      error_reporting(E_ALL | E_STRICT);
    }
}
