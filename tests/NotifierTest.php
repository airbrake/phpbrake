<?php
namespace NotifierTests;

class NotifierMock extends \Airbrake\Notifier
{
    public $url;
    public $data;
    public $notice;

    public function postNotice($url, $data) {
        $this->url = $url;
        $this->data = $data;
        $this->notice = json_decode($data, true);
    }
}

class NotifyTest extends \PHPUnit_Framework_TestCase
{
    private $notifier;

    public function setUp()
    {
        $this->notifier = new NotifierMock(array(
            'projectId' => 1,
            'projectKey' => 'api_key',
        ));
        $this->notifier->notify(new \Exception('hello'));
    }

    public function testPostsToURL()
    {
        $this->assertEquals(
            $this->notifier->url,
            'https://api.airbrake.io/api/v3/projects/1/notices?key=api_key'
        );
    }

    public function testPostsNotice()
    {
        $notice = $this->notifier->notice;
        $error = $notice['errors'][0];
        $this->assertEquals($error['type'], 'Exception');
        $this->assertEquals($error['message'], 'hello');

        $backtrace = $error['backtrace'];
        $wanted = array(array(
            'file' => dirname(dirname(__FILE__)) . '/vendor/phpunit/phpunit/src/Framework/TestCase.php',
            'line' => 742,
            'function' => 'NotifierTests\NotifyTest->setUp',
        ));
        for ($i = 0; $i < count($wanted); $i++) {
            $this->assertEquals($backtrace[$i], $wanted[$i]);
        }
    }
}

class FilterTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->notifier = new NotifierMock(array(
            'projectId' => 1,
            'projectKey' => 'api_key',
        ));
        $this->notifier->addFilter(function() {
            return false;
        });
        $this->notifier->notify(new \Exception('hello'));
    }

    public function testNoticeIsIgnored()
    {
        $this->assertNull($this->notifier->notice);
    }
}

class ModificationTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->notifier = new NotifierMock(array(
            'projectId' => 1,
            'projectKey' => 'api_key',
        ));
        $this->notifier->addFilter(function() {
            $notice['context']['environment'] = 'production';
            unset($notice['environment']);
            return $notice;
        });
        $this->notifier->notify(new \Exception('hello'));
    }

    public function testNoticeIsModified()
    {
        $notice = $this->notifier->notice;
        $this->assertEquals($notice['context']['environment'], 'production');
    }

    public function testEnvironmentIsUnset()
    {
        $notice = $this->notifier->notice;
        $this->assertFalse(isset($notice['environment']));
    }
}
