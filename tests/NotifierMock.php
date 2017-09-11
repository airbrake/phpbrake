<?php

namespace Airbrake\Tests;

use Airbrake\Notifier;
use GuzzleHttp\Promise\FulfilledPromise;

class NotifierMock extends Notifier
{
    public $url;
    public $notice;
    public $resp;

    public function __construct($opt)
    {
        parent::__construct($opt);
        $this->resp = new ResponseMock(201, '{"id":"12345"}');
    }

    public function sendRequest($req)
    {
        $this->url = (string) $req->getUri();
        $data = $req->getBody()->getContents();
        $this->notice = json_decode($data, true);
        return $this->resp;
    }

    public function sendRequestAsync($req)
    {
        $this->url = (string) $req->getUri();
        $data = $req->getBody()->getContents();
        $this->notice = json_decode($data, true);
        return new FulfilledPromise($this->resp);
    }
}
