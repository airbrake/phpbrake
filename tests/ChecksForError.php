<?php

namespace Airbrake\Tests;

trait ChecksForError
{
    /** @dataProvider undefinedVarErrorProvider */
    public function testPostsError($notifier)
    {
        $notice = $notifier->notice;
        $error = $notice['errors'][0];
        $this->assertEquals('Airbrake\Errors\Notice', $error['type']);
        $this->assertEquals('Undefined variable: undefinedVar', $error['message']);
    }

    /** @dataProvider undefinedVarErrorProvider */
    public function testPostsErrorBacktrace($notifier)
    {
        $backtrace = $notifier->notice['errors'][0]['backtrace'];
        $this->assertCount(19, $backtrace);

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

    abstract public function undefinedVarErrorProvider();
}
