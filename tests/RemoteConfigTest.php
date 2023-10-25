<?php

namespace Airbrake\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class RemoteConfigTest extends TestCase
{
    private $projectId = 555;
    private $remoteConfig;
    private $remoteErrorConfig;
    private $responseBody;
    
    protected function setUp(): void
    {
        $this->remoteConfig = new RemoteConfigMock($this->projectId);
        $this->remoteErrorConfig = [
            "name" => "errors",
            "endpoint" => "remote-config.airbrake.io",
            "enabled" => true
        ];
        $this->responseBody = [
            "settings" => [$this->remoteErrorConfig]
        ];
    }

    public function testWithValidRemoteConfig()
    {
        $this->mockRemoteResponse(200, $this->responseBody);

        $this->assertSame(
            $this->remoteConfig->errorConfig(),
            [
                "host" => 'remote-config.airbrake.io',
                "enabled" => true
            ]
        );
    }

    public function testWithErrorsDisabled()
    {
        $this->remoteErrorConfig['enabled'] = false;
        $this->responseBody['settings'] = [ $this->remoteErrorConfig ];
        $this->mockRemoteResponse(200, $this->responseBody);

        $this->assertSame(
            $this->remoteConfig->errorConfig(),
            [
                "host" => 'remote-config.airbrake.io',
                "enabled" => false
            ]
        );
    }

    public function testWithNoKnownHost()
    {
        $this->remoteErrorConfig['endpoint'] = null;
        $this->responseBody['settings'] = [ $this->remoteErrorConfig ];
        $this->mockRemoteResponse(200, $this->responseBody);

        $this->assertSame(
            $this->remoteConfig->errorConfig(),
            [
                "host" => $this->remoteConfig::DEFAULT_CONFIG['host'],
                "enabled" => true
            ]
        );
    }

    public function testWithMissingHostConfig()
    {
        $this->responseBody['settings'] = [
            [
                "name" => 'error',
                "enabled" => false
            ]
        ];
        $this->mockRemoteResponse(200, $this->responseBody);

        $this->returnsTheDefaultConfig();
    }

    public function testWithoutErrorConfig()
    {
        $this->responseBody['settings'] = [
            [
                "name" => 'event',
                "enabled" => true
            ]
        ];
        $this->mockRemoteResponse(200, $this->responseBody);

        $this->returnsTheDefaultConfig();
    }

    public function testWithAnUnexpectedStatusCode()
    {
        $this->mockRemoteResponse(403, null);

        $this->returnsTheDefaultConfig();
    }

    public function testWithAnEmptyResponse()
    {
        $this->mockRemoteResponse(200, []);

        $this->returnsTheDefaultConfig();
    }

    public function testWhenTheRequestRaisesAnException()
    {
        $mockClient = $this
            ->createMock(\GuzzleHttp\Client::class);
        $mockClient
            ->method('request')
            ->will($this->throwException(new \Exception));
        $this->remoteConfig->setMockClient($mockClient);

        $this->returnsTheDefaultConfig();
    }

    public function testConfiguredWithExpectedCacheSettings()
    {
        $this->assertSame(
            'airbrake_cached_remote_config.json',
            $this->remoteConfig->tempCache->filename
        );
        $this->assertSame(600, $this->remoteConfig->tempCache->ttl);
    }

    public function testCannotWriteCache()
    {
        $this->remoteConfig->tempCache->mockCanWrite = false;

        $this->remoteConfig->errorConfig();

        $didNotReadFromCache = !$this->remoteConfig->tempCache->wasRead;
        $didNotWriteToCache = !$this->remoteConfig->tempCache->wasWritten;
        $this->assertTrue($didNotReadFromCache);
        $this->assertTrue($didNotWriteToCache);
        $this->returnsTheDefaultConfig();
    }

    public function testReadsFromCacheWhenNotExpired()
    {
        $this->mockRemoteResponse(200, $this->responseBody);
        $this->remoteConfig->tempCache->mockCanWrite = true;

        // write config to the cache
        $this->remoteConfig->errorConfig();
        $this->remoteConfig->tempCache->mockExpired = false;
        
        // read config from cache
        $this->remoteConfig->errorConfig();

        $this->assertTrue($this->remoteConfig->tempCache->wasRead);
        $this->assertSame(
            $this->responseBody,
            $this->remoteConfig->tempCache->lastReadValue,
        );
    }

    public function testWritesToCacheWhenExpired()
    {
        $this->mockRemoteResponse(200, $this->responseBody);
        $this->remoteConfig->tempCache->mockCanWrite = true;
        $this->remoteConfig->tempCache->mockExpired = true;

        $this->remoteConfig->errorConfig();

        $this->assertTrue($this->remoteConfig->tempCache->wasWritten);
        $this->assertSame(
            $this->responseBody,
            $this->remoteConfig->tempCache->lastWrittenValue,
        );
    }
    // RemoteConfigTest helpers for mocking and asserting when the default
    // config is returned.

    protected function returnsTheDefaultConfig()
    {
        $this->assertSame(
            $this->remoteConfig->errorConfig(),
            $this->remoteConfig::DEFAULT_CONFIG
        );
    }

    private function mockRemoteResponse($statusCode, $body)
    {
        $handler = new MockHandler([
            new Response($statusCode, [], json_encode($body))
        ]);
        $handlerStack = HandlerStack::create($handler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->remoteConfig->setMockClient($mockClient);
    }
}
