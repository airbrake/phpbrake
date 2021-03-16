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
    }

    public function errorConfig()
    {
        if (!$this->cachingNotPossible()) {
            return $this->defaultConfig;
        }

        if ($this->isCached()) {
            $config = $this->getCachedConfig();
        } else {
            $config = $this->fetchConfig();
            $this->writeConfigToCache($config);
        }

        return $this->parseErrorConfig($config);
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

    // Check if it's not possible to write the cache file to the system temp dir.
    protected function cachingNotPossible()
    {
        $file = $this->cacheFile();
        return is_writeable(dirname($file));
    }

    protected function isCached()
    {
        $file = $this->cacheFile();
        if (!is_file($file)) {
            return false;
        }

        $expiration_time = filemtime($file) + $this->cacheTTL();
        $cacheAlive = time() < $expiration_time;
        return $cacheAlive;
    }

    protected function getCachedConfig()
    {
        $use_associative_arrays = true;


        try {
            $value = file_get_contents($this->cacheFile());
            if ($value === false) {
                return null;
            }

            $config = json_decode($value, $use_associative_arrays);
            return $config;
        } catch (Exception $e) {
            unset($e);
            return null;
        }
    }

    protected function writeConfigToCache($config)
    {
        try {
            $wroteToFile = file_put_contents(
                $this->cacheFile(),
                json_encode($config),
                LOCK_EX
            );

            if ($wroteToFile === false) {
                return null;
            }
        } catch (Exception $e) {
            unset($e);
            return null;
        }
    }

    protected function remoteConfigCache()
    {
        return sys_get_temp_dir() . "/" . $this->cacheFile();
    }

    protected function cacheFile()
    {
        return sys_get_temp_dir() . '/' . 'airbrake_cached_remote_config.json';
    }

    protected function cacheTTL()
    {
        return 600; // Fetch a new remote config every 10 minutes.
    }
}
