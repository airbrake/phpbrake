<?php

namespace Airbrake\Tests;

use PHPUnit\Framework\TestCase;
use Airbrake\RemoteConfig;
use GuzzleHttp\Client;

class RemoteConfigTest extends TestCase
{
    private $projectId = 555;
    private $remoteConfigURL = 'https://notifier-configs.airbrake.io' .
        '/2020-06-18/config/555/config.json';
    private $remoteConfig;
    private $remoteErrorConfig;
    private $responseBody;
    private $defaultConfig = [
        "host" => 'api.airbrake.io',
        "enabled" => true
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
                "host" => $this->defaultConfig['host'],
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

    // RemoteConfigTest helpers for mocking and asserting when the default
    // config is returned.

    protected function returnsTheDefaultConfig()
    {
        $this->assertSame(
            $this->remoteConfig->errorConfig(),
            $this->defaultConfig
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
            ->with('GET', $this->remoteConfigURL)
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
