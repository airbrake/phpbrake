<?php

namespace Airbrake;

use GuzzleHttp\Client;

class RemoteConfig
{
    protected $remoteConfigURL;
    protected $httpClient;
    private $defaultConfig = [
        "host" => 'api.airbrake.io',
        "enabled" => true
    ];
    private $remoteConfigURLFormatString = 'https://notifier-configs.airbrake.io' .
        '/2020-06-18/config/%d/config.json';

    public function __construct($projectId)
    {
        $this->remoteConfigURL = $this->buildRemoteUrl($projectId);
        $this->httpClient = $this->newHTTPClient();
    }

    public function errorConfig()
    {
        return $this->fetchConfig();
    }

    /**
      The fetchConfig method returns the remote error config from s3 when
      everything goes right and returns the default config when there is an
      issue.
    **/
    private function fetchConfig()
    {
        try {
            $response = $this->httpClient->request('GET', $this->remoteConfigURL);
        } catch (\Exception $e) {
            unset($e); // $e is not used.
            return $this->defaultConfig;
        }

        if ($response->getStatusCode() != 200) {
            return $this->defaultConfig;
        }

        $body = $response->getBody();
        $config = json_decode($body, true);

        return $this->parseErrorConfig($config);
    }

    private function parseErrorConfig($config)
    {
        $array_not_found = is_array($config) == false;
        $config_not_found = array_key_exists("settings", $config) == false;
        if ($array_not_found || $config_not_found) {
            return $this->defaultConfig;
        }

        foreach ($config['settings'] as $cfg) {
            if (isset($cfg["name"]) && $cfg["name"] == "errors") {
                $errorConfig = $cfg;
            }
        };

        if (isset($errorConfig['endpoint'])) {
            $host = $errorConfig['endpoint'];
        } else {
            $host = $this->defaultConfig['host'];
        }

        if (isset($errorConfig['enabled'])) {
            $enabled = $errorConfig['enabled'];
        } else {
            $enabled = $this->defaultConfig['enabled'];
        }

        return array("host" => $host, "enabled" => $enabled);
    }

    private function buildRemoteUrl($projectId)
    {
        return sprintf(
            $this->remoteConfigURLFormatString,
            $projectId
        );
    }

    private function newHTTPClient()
    {
        return new Client(
            [
                'connect_timeout' => 2,
                'read_timeout' => 2,
                'timeout' => 2,
            ]
        );
    }
}
