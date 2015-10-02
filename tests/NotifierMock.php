<?php

namespace Airbrake\Tests;

use Airbrake\Notifier;

class NotifierMock extends Notifier
{
    public $resp = [
        'headers' => 'HTTP/1.1 201 Created',
        'data' => '{"id":"12345"}',
    ];

    public $url;
    public $data;
    public $notice;

    public function postNotice($url, $data)
    {
        $this->url = $url;
        $this->data = $data;
        $this->notice = json_decode($data, true);
        return $this->resp;
    }
}
