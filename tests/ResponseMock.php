<?php

namespace Airbrake\Tests;

class ResponseMock
{
    /**
     * @var string
     */
    private $statusCode;

    /**
     * @var string
     */
    private $body;

    public function __construct($statusCode, $body)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getBody()
    {
        return $this->body;
    }
}
