<?php

namespace Airbrake\Http;

use Airbrake\Exception;
use InvalidArgumentException;

class Factory
{
    /**
     * HTTP client generation.
     *
     * @param string|null $handler
     *
     * @throws Exception                If the cURL extension or the Guzzle client aren't available (if required).
     * @throws InvalidArgumentException If the http client handler isn't "default", "curl" or "guzzle"
     *
     * @return ClientInterface
     */
    public static function createHttpClient($handler = null)
    {
        if ($handler === 'guzzle') {
            if (!class_exists('GuzzleHttp\Client')) {
                throw new Exception('The Guzzle HTTP client must be included in order to use the "guzzle" handler.');
            }
            return new GuzzleClient();
        }

        if ($handler === 'curl') {
            if (!extension_loaded('curl')) {
                throw new Exception('The cURL extension must be loaded in order to use the "curl" handler.');
            }
            return new CurlClient();
        }

        if (!$handler || $handler === 'default') {
            return new DefaultClient();
        }

        throw new InvalidArgumentException('The http client handler must be set to "default", "curl" or "guzzle"');
    }
}
