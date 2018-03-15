<?php

namespace Airbrake\Tests;

trait ChecksForException
{
    /** @dataProvider exceptionProvider */
    public function testPostsException($notifier)
    {
        $notice = $notifier->notice;
        $error = $notice['errors'][0];
        $this->assertEquals('Exception', $error['type']);
        $this->assertEquals('hello', $error['message']);
    }

    /** @dataProvider exceptionProvider */
    public function testPostsExceptionBacktrace($notifier)
    {
        $backtrace = $notifier->notice['errors'][0]['backtrace'];
        $this->assertCount(18, $backtrace);

        $wanted = [[
            'file' => dirname(dirname(__FILE__)).'/tests/Troublemaker.php',
            'line' => 19,
            'function' => 'Airbrake\Tests\Troublemaker::doNewException',
            'code' => [
                17 => '    private static function doNewException()',
                18 => '    {',
                19 => "        return new \Exception('hello');",
                20 => '    }',
                21 => '',
            ],
        ], [
            'file' => dirname(dirname(__FILE__)).'/tests/Troublemaker.php',
            'line' => 24,
            'function' => 'Airbrake\Tests\Troublemaker::newException',
            'code' => [
                22 => '    public static function newException()',
                23 => '    {',
                24 => '        return self::doNewException();',
                25 => '    }',
                26 => '',
            ],
        ]];
        for ($i = 0; $i < count($wanted); $i++) {
            $this->assertEquals($wanted[$i], $backtrace[$i]);
        }
    }

    /** @dataProvider exceptionProvider */
    public function testPostsNotifier($notifier)
    {
        $this->assertEquals(
            'phpbrake',
            $notifier->notice['context']['notifier']['name']
        );
    }

    /** @dataProvider exceptionProvider */
    public function testContextURL($notifier)
    {
        $this->assertEquals(
            'http://airbrake.io/hello',
            $notifier->notice['context']['url']
        );
    }

    /** @dataProvider exceptionProvider */
    public function testPostsToURL($notifier)
    {
        $this->assertEquals(
            'https://api.airbrake.io/api/v3/projects/1/notices',
            $notifier->url
        );
    }

    abstract public function exceptionProvider();
}
