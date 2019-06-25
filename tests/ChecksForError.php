<?php

namespace Airbrake\Tests;

trait ChecksForError
{
    /** @dataProvider undefinedVarErrorProvider */
    public function testPostsError($notifier)
    {
        $notice = $notifier->notice;
        $error = $notice['errors'][0];
        $this->assertEquals('E_NOTICE', $error['type']);
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
            'code' => [
                7 => '    private static function doEchoUndefinedVar()',
                8 => '    {',
                9 => '        echo $undefinedVar;',
                10 => '    }',
                11 => '',
            ],
        ], [
            'file' => dirname(dirname(__FILE__)).'/tests/Troublemaker.php',
            'line' => 14,
            'function' => 'Airbrake\Tests\Troublemaker::echoUndefinedVar',
            'code' => [
                12 => '    public static function echoUndefinedVar()',
                13 => '    {',
                14 => '        self::doEchoUndefinedVar();',
                15 => '    }',
                16 => '',
            ],
        ]];
        for ($i = 0; $i < count($wanted); $i++) {
            $this->assertEquals($wanted[$i], $backtrace[$i]);
        }
    }

    abstract public function undefinedVarErrorProvider();
}
