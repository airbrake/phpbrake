<?php

namespace Airbrake\Tests;

use Airbrake\Notifier;

class NotifierMock extends Notifier
{
    public $url;
    public $data;
    public $notice;

    public $resp;

    public function __construct($opt)
    {
        parent::__construct($opt);
        $this->resp = new ResponseMock(201, '{"id":"12345"}');
        ;
    }

    public function postNotice($url, $data)
    {
        $this->url = $url;
        $this->data = $data;
        $this->notice = json_decode($data, true);
        return $this->resp;
    }
}
