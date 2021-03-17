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
}
