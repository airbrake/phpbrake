<?php

namespace Airbrake\Tests;

use Airbrake\RemoteConfig;

class RemoteConfigMock extends RemoteConfig
{
    public function __construct($projectId)
    {
        parent::__construct($projectId);
    }

    public function setMockClient($mockClient)
    {
        $this->httpClient = $mockClient;
    }
}
