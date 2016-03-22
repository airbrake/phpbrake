<?php

namespace Airbrake\Tests;

use PHPUnit_Framework_TestCase;

class FilterTest extends PHPUnit_Framework_TestCase
{
    private function makeNotifierWithFilter(callable $filter)
    {
        $notifier = new NotifierMock([
            'projectId' => 1,
            'projectKey' => 'api_key',
        ]);
        $notifier->addFilter($filter);
        $notifier->notify(new \Exception('hello'));
        return $notifier;
    }

    /**
     * @dataProvider negativeFilterProvider
     */
    public function testNoticeIsIgnored($filter, $comment)
    {
        $notifier = $this->makeNotifierWithFilter($filter);
        $this->assertNull($notifier->notice, $comment);
    }

    public function negativeFilterProvider()
    {
        return [
            [
                function () {
                    return;
                },
                'When filter returns null'
            ],
            [
                function () {
                    return false;
                },
                'When filter returns false'
            ],
        ];
    }

    /**
     * @dataProvider filterFullNotifierProvider
     */
    public function testNoticeIsModified($notifier)
    {
        $this->assertEquals('production', $notifier->notice['context']['environment']);
    }

    /**
     * @dataProvider filterFullNotifierProvider
     */
    public function testEnvironmentIsUnset($notifier)
    {
        $this->assertFalse(isset($notifier->notice['environment']));
    }

    public function filterFullNotifierProvider()
    {
        return [[$this->makeNotifierWithFilter(function () {
            $notice['context']['environment'] = 'production';
            unset($notice['environment']);

            return $notice;
        })]];
    }
}
