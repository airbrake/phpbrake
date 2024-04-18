<?php

namespace Airbrake;

use GuzzleHttp\Client;

class RemoteConfig
{
    const DEFAULT_CONFIG = [
        "host" => 'api.airbrake.io',
        "enabled" => true
    ];
    protected $remoteConfigURL;
    protected $httpClient;
    protected $tempCache;
    protected $tempCacheFilename = 'airbrake_cached_remote_config.json';
    protected $tempCacheTTL = 600;
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
            if (!$this->tempCache->canWrite()) {
                return self::DEFAULT_CONFIG;
            }

            if ($this->tempCache->expired()) {
                $config = $this->fetchConfig();
                $this->tempCache->write($config);
            } else {
                $config = $this->tempCache->read();
            }
        } catch (\Exception $e) {
            unset($e); // $e is not used.
            return self::DEFAULT_CONFIG;
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
            return self::DEFAULT_CONFIG;
        }

        if ($response->getStatusCode() != 200) {
            return self::DEFAULT_CONFIG;
        }

        $body = $response->getBody();
        $config = json_decode($body, true);

        return $config;
    }

    private function parseErrorConfig($config)
    {
        $config_found = array_key_exists("settings", (array) $config);
        if (!$config_found) {
            return self::DEFAULT_CONFIG;
        }

        foreach ($config['settings'] as $s) {
            if (isset($s["name"]) && $s["name"] == "errors") {
                $errorConfig = $s;
                break;
            }
        };

        if (isset($errorConfig['endpoint'])) {
            $host = $errorConfig['endpoint'];
        } else {
            $host = self::DEFAULT_CONFIG['host'];
        }

        if (isset($errorConfig['enabled'])) {
            $enabled = $errorConfig['enabled'];
        } else {
            $enabled = self::DEFAULT_CONFIG['enabled'];
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
                'connect_timeout' => 10,
                'read_timeout' => 10,
                'timeout' => 10,
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
