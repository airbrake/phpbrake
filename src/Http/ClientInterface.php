<?php

namespace Airbrake\Http;

/**
 * Interface ClientInterface
 */
interface ClientInterface
{
    /**
     * Sends a request
     *
     * @param string $url  The endpoint to send the request to.
     * @param string $data The body of the request as json encoded string
     *
     * @return array Raw response from the server.
     *
     * @throws \Airbrake\Exception
     */
    public function send($url, $data);
}
