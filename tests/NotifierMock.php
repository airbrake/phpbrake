<?php

namespace Airbrake\Tests;

use Airbrake\Notifier;

class NotifierMock extends Notifier
{
    public $url;
    public $data;
    public $notice;

    public function postNotice($url, $data)
    {
        $this->url = $url;
        $this->data = $data;
        $this->notice = json_decode($data, true);
        return array(
            'status' => 'HTTP/1.1 201 CREATED',
            'data' => '{"id":"12345"}',
        );
    }
}
