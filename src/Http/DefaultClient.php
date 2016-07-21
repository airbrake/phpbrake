<?php

namespace Airbrake\Http;

/**
 * Class DefaultClient
 *
 * @package Airbrake
 */
class DefaultClient implements ClientInterface
{
    /**
     * @inheritdoc
     */
    public function send($url, $data)
    {
        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => $data,
                'ignore_errors' => true,
            ],
        ];
        $context = stream_context_create($options);
        $body = file_get_contents($url, false, $context);
        $headers = (isset($http_response_header) ? $http_response_header : null);
        return ['headers' => $headers, 'data' => $body];
    }
}
