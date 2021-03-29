<?php

namespace Airbrake\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

class RemoteConfigTest extends TestCase
{
    private $projectId = 555;
    private $remoteConfigURL = 'https://notifier-configs.airbrake.io' .
        '/2020-06-18/config/555/config.json';
    private $remoteConfig;
    private $remoteErrorConfig;
    private $responseBody;
    private $notifierInfo = [
        'notifier_name' => 'phpbrake',
        'notifier_version' => AIRBRAKE_NOTIFIER_VERSION,
        'os' => PHP_OS,
        'language' => "PHP" . PHP_VERSION
    ];

    protected function setUp()
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
            ->getMockBuilder(GuzzleHttp\Client::class)
            ->setMethods(['request'])
            ->getMock();
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
        $this->remoteConfig->tempCache->mockCanWrite = true;
        // write config to the cache
        $this->remoteConfig->errorConfig();

        $this->remoteConfig->tempCache->mockExpired = false;
        // read config from cache
        $config = $this->remoteConfig->errorConfig();

        $this->assertTrue($this->remoteConfig->tempCache->wasRead);
        $this->assertSame(
            $this->remoteConfig->tempCache->lastReadValue,
            $config
        );
    }

    public function testWritesToCacheWhenExpired()
    {
        $this->remoteConfig->tempCache->mockCanWrite = true;
        $this->remoteConfig->tempCache->mockExpired = true;

        $config = $this->remoteConfig->errorConfig();

        $this->assertTrue($this->remoteConfig->tempCache->wasWritten);
        $this->assertSame(
            $this->remoteConfig->tempCache->lastWrittenValue,
            $config
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
        $mockResponse = $this->createMockResponse($statusCode, $body);
        $mockClient = $this->createMockClient($mockResponse);
        $this->remoteConfig->setMockClient($mockClient);
    }

    private function createMockClient($mockResponse)
    {
        $mockClient = $this
            ->getMockBuilder(GuzzleHttp\Client::class)
            ->setMethods(['request'])
            ->getMock();
        $mockClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->remoteConfigURL,
                ['query' => $this->notifierInfo]
            )
            ->willReturn($mockResponse);
        return $mockClient;
    }

    private function createMockResponse($statusCode, $body)
    {
        $mockResponse = $this
            ->getMockBuilder(GuzzleHttp\Psr7\Response::class)
            ->setMethods(['getStatusCode', 'getBody'])
            ->getMock();
        $mockResponse
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn($statusCode);
        if (isset($body)) {
            $mockResponse
                ->expects($this->once())
                ->method('getBody')
                ->willReturn(json_encode($body));
        }

        return $mockResponse;
    }
}
