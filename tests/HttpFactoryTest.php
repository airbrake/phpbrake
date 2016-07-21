<?php

namespace Airbrake\Tests;

use PHPUnit_Framework_TestCase;

class HttpFactoryTest extends PHPUnit_Framework_TestCase
{
    public function testGuzzleClient()
    {
        if (!class_exists('GuzzleHttp\Client')) {
            $this->markTestSkipped('Guzzle client is not available.');
        }
        
        $client = \Airbrake\Http\Factory::createHttpClient('guzzle');
        $this->assertInstanceOf('\Airbrake\Http\GuzzleClient', $client);
    }

    public function testCurlClient()
    {
        $client = \Airbrake\Http\Factory::createHttpClient('curl');
        $this->assertInstanceOf('\Airbrake\Http\CurlClient', $client);
    }

    public function testDefaultClient()
    {
        $client = \Airbrake\Http\Factory::createHttpClient('default');
        $this->assertInstanceOf('\Airbrake\Http\DefaultClient', $client);

        $client = \Airbrake\Http\Factory::createHttpClient();
        $this->assertInstanceOf('\Airbrake\Http\DefaultClient', $client);
    }

    public function testUndefineClient()
    {
        $this->setExpectedException('InvalidArgumentException');
        $client = \Airbrake\Http\Factory::createHttpClient('undefined client');
    }
}
