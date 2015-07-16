<?php
namespace MonologHandlerTests;

class NotifierMock extends \Airbrake\Notifier
{
    public $url;
    public $data;
    public $notice;

    public function postNotice($url, $data)
    {
        $this->url = $url;
        $this->data = $data;
        $this->notice = json_decode($data, true);
    }
}

class MonologHandlerTest extends \PHPUnit_Framework_TestCase {
    public function setUp()
    {
        $this->notifier = new NotifierMock(array(
            'projectId' => 1,
            'projectKey' => 'api_key',
        ));

        $log = new \Monolog\Logger('billing');
        $log->pushHandler(new \Airbrake\MonologHandler($this->notifier));

        $log->addError('charge failed', array('client_id' => 123));
    }

    public function testError()
    {
        $error = $this->notifier->notice['errors'][0];
        $this->assertEquals($error['type'], 'billing.ERROR');
        $this->assertEquals($error['message'], 'charge failed');
    }

    public function testBacktrace()
    {
        $backtrace = $this->notifier->notice['errors'][0]['backtrace'];
        $wanted = array(array(
          'file' => __FILE__,
          'line' => 29,
          'function' => 'Monolog\Logger->addError',
        ));
        for ($i = 0; $i < count($wanted); $i++) {
            $this->assertEquals($backtrace[$i], $wanted[$i]);
        }
    }

    public function testParams()
    {
        $params = $this->notifier->notice['params'];
        $this->assertEquals($params, array(
            'monolog_context' => array(
                'client_id' => 123,
            ),
        ));
    }
}
