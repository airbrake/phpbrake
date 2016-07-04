<?php

namespace Airbrake\Http;

/**
 * Class CurlClient
 *
 * @package Airbrake
 */
class CurlClient implements ClientInterface
{
    /**
     * @inheritdoc
     */
    public function send($url, $data)
    {
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data),
            ],
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);
        $response = curl_exec($ch);

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = trim(mb_substr($response, 0, $headerSize));
        $body = trim(mb_substr($response, $headerSize));

        curl_close($ch);
        return ['headers' => $headers, 'data' => $body];
    }
}
