<?php

namespace Airbrake;

use GuzzleHttp\Client;

class RemoteConfig
{
    protected $remoteConfigURL;
    protected $httpClient;
    protected $tempCache;
    protected $tempCacheFilename = 'airbrake_cached_remote_config.json';
    protected $tempCacheTTL = 600;
    private $defaultConfig = [
        "host" => 'api.airbrake.io',
        "enabled" => true
    ];
    private $remoteConfigURLFormatString = 'https://notifier-configs.airbrake.io' .
        '/2020-06-18/config/%d/config.json';
    private $notifierInfo = [
        'notifier_name' => 'phpbrake',
        'notifier_version' => AIRBRAKE_NOTIFIER_VERSION,
        'os' => PHP_OS,
        'language' => "PHP" . PHP_VERSION
    ];

    public function __construct($projectId)
    {
        $this->remoteConfigURL = $this->buildRemoteUrl($projectId);
        $this->httpClient = $this->newHTTPClient();
        $this->tempCache = $this->newTempCache();
    }

    public function errorConfig()
    {
        $config = $this->getConfigFromCacheOrFetch();
        return $this->parseErrorConfig($config);
    }

    private function getConfigFromCacheOrFetch()
    {
        try {
            if ($this->tempCache->canWrite() == false) {
                return $this->defaultConfig;
            }

            if ($this->tempCache->expired()) {
                $config = $this->fetchConfig();
                $this->tempCache->write($config);
            } else {
                $config = $this->tempCache->read();
            }
        } catch (\Exception $e) {
            unset($e); // $e is not used.
            return $this->defaultConfig;
        }

        return $config;
    }

    /**
      The fetchConfig method returns the remote error config from s3 when
      everything goes right and returns the default config when there is an
      issue.
    **/
    private function fetchConfig()
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                $this->remoteConfigURL,
                ['query' => $this->notifierInfo]
            );
        } catch (\Exception $e) {
            unset($e); // $e is not used.
            return $this->defaultConfig;
        }

        if ($response->getStatusCode() != 200) {
            return $this->defaultConfig;
        }

        $body = $response->getBody();
        $config = json_decode($body, true);

        return $config;
    }

    private function parseErrorConfig($config)
    {
        $config_not_found = array_key_exists("settings", (array) $config) == false;
        if ($config_not_found) {
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

    protected function newTempCache()
    {
        return new TempCache(
            $this->tempCacheFilename,
            $this->tempCacheTTL
        );
    }
}
