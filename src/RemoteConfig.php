<?php

namespace Airbrake;

use GuzzleHttp\Client;

class RemoteConfig
{
    private $defaultConfig = array(
      "host" => 'api.airbrake.io',
      "enabled" => true
    );

    public function __construct($projectId)
    {
        $this->projectId = $projectId;
        $this->remoteConfigURL = $this->buildRemoteUrl($projectId);
        $this->httpClient = $this->newHTTPClient();
    }

    public function errorConfig()
    {
        return $this->fetchConfig();
    }

    /**
      fetchConfig returns the remote error config from s3 when everything goes
      right and the defaultConfig when there is an issue.
    **/
    private function fetchConfig()
    {
        try {
            $response = $this->httpClient->request('GET', $this->remoteConfigURL);
        } catch (Exception $e) {
            unset($e); // $e is not used.
            return $this->defaultConfig();
        }

        if ($response->getStatusCode() != 200) {
          return $this->defaultConfig();
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
            return $this->defaultConfig();
        }

        foreach ($config['settings'] as $config) {
            if (isset($config["name"]) && $config["name"] == "errors") {
                $errorConfig = $config;
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
        return 'https://v1-production-notifier-configs.s3.amazonaws.com' .
          "/2020-06-18/config/{$projectId}/config.json";
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
