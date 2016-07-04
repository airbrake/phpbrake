<?php

namespace Airbrake\Http;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class GuzzleClient implements ClientInterface
{
    /**
     * @var \GuzzleHttp\Client The Guzzle client.
     */
    protected $guzzleClient;

    /**
     * @param \GuzzleHttp\Client|null The Guzzle client.
     */
    public function __construct(Client $guzzleClient = null)
    {
        $this->guzzleClient = $guzzleClient ?: new Client();
    }

    /**
     * @inheritdoc
     */
    public function send($url, $data)
    {
        $response = $this->guzzleClient->request('POST', $url, [
            'headers' => [
                'Content-type' => 'application/json',
            ],
            'body' => $data,
        ]);

        $headers = $this->getHeadersAsString($response);
        $body = $response->getBody();

        return ['headers' => $headers, 'data' => $body];
    }

    /**
     * Returns the Guzzle array of headers as a string.
     *
     * @param ResponseInterface $response The Guzzle response.
     *
     * @return string
     */
    public function getHeadersAsString(ResponseInterface $response)
    {
        $headers = $response->getHeaders();
        $rawHeaders = [];
        foreach ($headers as $name => $values) {
            $rawHeaders[] = $name . ": " . implode(", ", $values);
        }

        return implode("\r\n", $rawHeaders);
    }
}
