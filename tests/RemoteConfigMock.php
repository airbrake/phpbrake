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

    protected function writeConfigToCache($config)
    {
        // Don't write to the cache in the tests.
    }

    protected function isCached()
    {
        // Don't try to read from the cache in the tests.
        return false;
    }
}
