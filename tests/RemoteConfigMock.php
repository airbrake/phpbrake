<?php

namespace Airbrake\Tests;

use Airbrake\RemoteConfig;

class RemoteConfigMock extends RemoteConfig
{
    public $tempCache;

    public function __construct($projectId)
    {
        parent::__construct($projectId);
        $this->tempCache = new TempCacheMock(
            $this->tempCacheFilename,
            $this->tempCacheTTL
        );
    }

    public function setMockClient($mockClient)
    {
        $this->httpClient = $mockClient;
    }

    public function errorConfig()
    {
        if (isset($this->mockErrorConfig)) {
            return $this->mockErrorConfig;
        }

        return parent::errorConfig();
    }

    // This is a helper function to mock the return value of the
    // errorConfig method.
    public function mockErrorConfig($host = "api.airbrake.io", $enabled = true)
    {
        $this->mockErrorConfig = [
            "host" => $host,
            "enabled" => $enabled
        ];
    }
}
