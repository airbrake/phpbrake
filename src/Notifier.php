<?php

namespace Airbrake;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;

define('HTTP_STATUS_UNAUTHORIZED', 401);
define('HTTP_STATUS_TOO_MANY_REQUESTS', 429);

define('ERR_UNAUTHORIZED', 'phpbrake: unauthorized: project id or key are wrong');
define('ERR_IP_RATE_LIMITED', 'phpbrake: IP is rate limited');


/**
 * Airbrake exception notifier.
 */
class Notifier
{
    private static $instanceCount = 0;

    /**
     * @var string
     */
    protected $noticesURL;

    /**
     * @var array
     */
    private $opt;

    /**
     * @var callable[]
     */
    private $filters = [];

    /**
     * Http client
     * @var GuzzleHttp\ClientInterface
     */
    private $httpClient;

    /**
     * @var int
     */
    private $rateLimitReset;

    private $codeHunk;
    private $context;

    /**
     * Constructor
     *
     * Available options are:
     *  - projectId     project id
     *  - projectKey    project key
     *  - host          airbrake api host e.g.: 'api.airbrake.io' or 'http://errbit.example.com'
     *  - appVersion
     *  - environment
     *  - rootDirectory
     *  - keysBlacklist list of keys containing sensitive information that must be filtered out
     *  - httpClient    http client implementing GuzzleHttp\ClientInterface
     *
     * @param array $opt the options
     * @throws \Airbrake\Exception
     */
    public function __construct($opt)
    {
        if (empty($opt['projectId']) || empty($opt['projectKey'])) {
            throw new Exception('phpbrake: Notifier requires projectId and projectKey');
        }

        $this->opt = array_merge([
          'host' => 'api.airbrake.io',
          'keysBlacklist' => ['/password/i', '/secret/i'],
        ], $opt);
        $this->httpClient = $this->newHTTPClient();
        $this->noticesURL = $this->buildNoticesURL();
        $this->codeHunk = new CodeHunk();
        $this->context = $this->buildContext();

        if (array_key_exists('keysBlacklist', $this->opt)) {
            $this->addFilter(function ($notice) {
                $noticeKeys = array('context', 'params', 'session', 'environment');
                foreach ($noticeKeys as $key) {
                    if (array_key_exists($key, $notice)) {
                        $this->filterKeys($notice[$key], $this->opt['keysBlacklist']);
                    }
                }
                return $notice;
            });
        }

        if (self::$instanceCount === 0) {
            Instance::set($this);
        }
        self::$instanceCount++;
    }

    private function buildContext()
    {
        $context = [
            'notifier' => [
                'name' => 'phpbrake',
                'version' => '0.6.0',
                'url' => 'https://github.com/airbrake/phpbrake',
            ],
            'os' => php_uname(),
            'language' => 'php ' . phpversion(),
        ];

        if (array_key_exists('appVersion', $this->opt)) {
            $context['version'] = $this->opt['appVersion'];
        }
        if (array_key_exists('environment', $this->opt)) {
            $context['environment'] = $this->opt['environment'];
        }
        if (($hostname = gethostname()) !== false) {
            $context['hostname'] = $hostname;
        }
        if (array_key_exists('revision', $this->opt)) {
            $context['revision'] = $this->opt['revision'];
        } else if (array_key_exists('SOURCE_VERSION', $_ENV)) {
            // https://devcenter.heroku.com/changelog-items/630
            $context['revision'] = $_ENV['SOURCE_VERSION'];
        }

        if (array_key_exists('rootDirectory', $this->opt)) {
            $context['rootDirectory'] = $this->opt['rootDirectory'];
            $this->addFilter(function ($notice) {
                return $this->rootDirectoryFilter($notice);
            });

            if (!array_key_exists('revision', $context)) {
                $rev = $this->gitRevision($this->opt['rootDirectory']);
                if ($rev) {
                    $context['revision'] = $rev;
                }
            }
        }

        return $context;
    }

    private function newHTTPClient()
    {
        if (array_key_exists('httpClient', $this->opt)) {
            if ($this->opt['httpClient'] instanceof GuzzleHttp\ClientInterface) {
                return $this->opt['httpClient'];
            }
            throw new Exception('phpbrake: httpClient must implement GuzzleHttp\ClientInterface');
        }
        return new Client([
            'connect_timeout' => 5,
            'read_timeout' => 5,
            'timeout' => 5,
        ]);
    }

    /**
     * Appends filter to the list.
     *
     * Filter is a callback that accepts notice. Filter can modify passed
     * notice or return null if notice must be ignored.
     *
     * @param callable $filter Filter callback
     */
    public function addFilter(callable $filter)
    {
        $this->filters[] = $filter;
    }

    private function backtrace($exc)
    {
        $backtrace = [];

        if ($exc->getFile() !== '' && $exc->getLine() !== 0) {
            $backtrace[] = $this->populateCode([
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'function' => ''
            ]);
        }

        $trace = $exc->getTrace();
        $count = count($trace);
        if ($count === 0) {
            return $backtrace;
        }
        $pushFunc = isset($trace[$count-1]['function']);

        foreach ($trace as $frame) {
            $func = '';
            if (isset($frame['function'])) {
                $func = $frame['function'];
            }
            if (isset($frame['class']) && isset($frame['type'])) {
                $func = $frame['class'] . $frame['type'] . $func;
            }

            $count = count($backtrace);
            if ($pushFunc && $count > 0) {
                $backtrace[$count-1]['function'] = $func;
                $func = '';
            }

            $backtrace[] = $this->populateCode([
                'file' => isset($frame['file']) ? $frame['file'] : '',
                'line' => isset($frame['line']) ? $frame['line'] : 0,
                'function' => $func
            ]);
        }

        return $backtrace;
    }

    private function populateCode(array $frame)
    {
        if (!$frame['file'] || !$frame['line']) {
            return $frame;
        }

        $code = $this->codeHunk->get($frame['file'], $frame['line']);
        if ($code !== null) {
            $frame['code'] = $code;
        }

        return $frame;
    }

    /**
     * Builds Airbrake notice from exception.
     *
     * @param \Throwable|\Exception $exc Exception or class that implements similar interface.
     * @return array Airbrake notice
     */
    public function buildNotice($exc)
    {
        $error = [
            'type' => get_class($exc),
            'message' => $exc->getMessage(),
            'backtrace' => $this->backtrace($exc)
        ];

        $context = $this->context;
        if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
            $scheme = isset($_SERVER['HTTPS']) ? 'https' : 'http';
            $context['url'] = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $context['userAgent'] = $_SERVER['HTTP_USER_AGENT'];
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $context['userAddr'] = trim(array_pop($ips));
        } else if (isset($_SERVER['REMOTE_ADDR'])) {
            $context['userAddr'] = $_SERVER['REMOTE_ADDR'];
        }

        $notice = [
            'errors' => [$error],
            'context' => $context,
        ];
        if (!empty($_REQUEST)) {
            $notice['params'] = $_REQUEST;
        }
        if (!empty($_SESSION)) {
            $notice['session'] = $_SESSION;
        }

        return $notice;
    }

    /**
     * Sends notice to Airbrake.
     *
     * It returns notice with 2 possible new keys:
     * - ['id' => '12345'] - notice id on success.
     * - ['error' => 'error message'] - error message on failure.
     *
     * @param array $notice Airbrake notice
     * @return array Airbrake notice
     */
    public function sendNotice($notice)
    {
        $notice = $this->filterNotice($notice);
        if (isset($notice['error'])) {
            return $notice;
        }

        if (time() < $this->rateLimitReset) {
            $notice['error'] = ERR_IP_RATE_LIMITED;
            return $notice;
        }

        $req = $this->newHttpRequest($notice);
        $resp = $this->sendRequest($req);
        return $this->processHttpResponse($notice, $resp);
    }

    protected function filterNotice($notice)
    {
        foreach ($this->filters as $filter) {
            $r = $filter($notice);
            if ($r === null || $r === false) {
                $notice['error'] = 'phpbrake: notice is ignored';
                return $notice;
            }
            $notice = $r;
        }
        return $notice;
    }

    protected function newHttpRequest($notice)
    {
        $headers = [
            'Content-type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->opt['projectKey'],
        ];
        $body = json_encode($notice);
        return new \GuzzleHttp\Psr7\Request('POST', $this->noticesURL, $headers, $body);
    }

    protected function sendRequest($req)
    {
        return $this->httpClient->send($req, ['http_errors' => false]);
    }

    protected function processHttpResponse($notice, $resp)
    {
        $statusCode = $resp->getStatusCode();

        if ($statusCode == HTTP_STATUS_UNAUTHORIZED) {
            $notice['error'] = ERR_UNAUTHORIZED;
            return $notice;
        }

        if ($statusCode == HTTP_STATUS_TOO_MANY_REQUESTS) {
            $h = $resp->getHeader('X-RateLimit-Delay');
            if (count($h) > 0) {
                $this->rateLimitReset = time() + intval($h[0]);
            }
            $notice['error'] = ERR_IP_RATE_LIMITED;
            return $notice;
        }

        $body = $resp->getBody();
        $res = json_decode($body, true);

        if (isset($res['id'])) {
            $notice['id'] = $res['id'];
            return $notice;
        }

        if (isset($res['message'])) {
            $notice['error'] = $res['message'];
            return $notice;
        }

        $notice['error'] = $body;
        return $notice;
    }

    /**
     * Notifies Airbrake about exception.
     *
     * Under the hood notify is a shortcut for buildNotice and sendNotice.
     *
     * @param \Throwable|\Exception $exc Error to be reported to Airbrake.
     * @return array Airbrake notice
     */
    public function notify($exc)
    {
        $notice = $this->buildNotice($exc);
        return $this->sendNotice($notice);
    }

    /**
     * @param \Throwable|\Exception $exc Error to be reported to Airbrake.
     * @return GuzzleHttp\Promise\PromiseInterface Promise resolved with $notice
     * or rejected with $notice['error'].
     */
    public function notifyAsync($exc)
    {
        $notice = $this->buildNotice($exc);
        return $this->sendNoticeAsync($notice);
    }

    /**
     * @return GuzzleHttp\Promise\PromiseInterface Promise resolved with $notice
     * or rejected with $notice['error'].
     */
    public function sendNoticeAsync($notice)
    {
        $notice = $this->filterNotice($notice);
        if (isset($notice['error'])) {
            return $notice;
        }

        if (time() < $this->rateLimitReset) {
            $notice['error'] = ERR_IP_RATE_LIMITED;
            return $notice;
        }

        $req = $this->newHttpRequest($notice);
        $sendPromise = $this->sendRequestAsync($req);

        $promise = new Promise(function () use (&$sendPromise) {
            $sendPromise->wait();
        }, function () use (&$sendPromise) {
            $sendPromise->cancel();
        });

        $sendPromise->then(function ($resp) use (&$promise, &$notice) {
            $notice = $this->processHttpResponse($notice, $resp);
            if (isset($notice['error'])) {
                $promise->reject($notice['error']);
            } else {
                $promise->resolve($notice);
            }
        }, function ($reason) use (&$notice) {
            $notice['error'] = $reason;
            $promise->reject($reason);
        });

        return $promise;
    }

    protected function sendRequestAsync($req)
    {
        return $this->httpClient->sendAsync($req, ['http_errors' => false]);
    }

    /**
     * Builds notices URL.
     *
     * @return string
     */
    protected function buildNoticesURL()
    {
        $schemeAndHost = $this->opt['host'];
        if (!preg_match('~^https?://~i', $schemeAndHost)) {
            $schemeAndHost = "https://$schemeAndHost";
        }
        return sprintf(
            '%s/api/v3/projects/%d/notices',
            $schemeAndHost,
            $this->opt['projectId']
        );
    }

    protected function rootDirectoryFilter($notice)
    {
        $projectRoot = $this->opt['rootDirectory'];
        $notice['context']['rootDirectory'] = $projectRoot;
        foreach ($notice['errors'] as &$error) {
            if (empty($error['backtrace'])) {
                continue;
            }
            foreach ($error['backtrace'] as &$frame) {
                $frame['file'] = preg_replace("~^$projectRoot~", '/PROJECT_ROOT', $frame['file']);
            }
        }
        return $notice;
    }

    protected function gitRevision($dir)
    {
        $headFile = join(DIRECTORY_SEPARATOR, [$dir, '.git', 'HEAD']);
        $head = @file_get_contents($headFile);
        if ($head === false) {
            return null;
        }

        $head = rtrim($head);
        $prefix = 'ref: ';
        if (strpos($head, $prefix) === false) {
            return $head;
        }
        $head = substr($head, strlen($prefix));

        $refFile = join(DIRECTORY_SEPARATOR, [$dir, '.git', $head]);
        $rev = @file_get_contents($refFile);
        if ($rev !== false) {
            return rtrim($rev);
        }

        $refsFiles = join(DIRECTORY_SEPARATOR, [$dir, '.git', 'packed-refs']);
        $handle = fopen($refsFiles, 'r');
        if (!$handle) {
            return null;
        }

        while (($line = fgets($handle)) !== false) {
            if (!$line || $line[0] === '#' || $line[0] === '^') {
                continue;
            }

            $parts = explode(' ', rtrim($line));
            if (count($parts) !== 2) {
                continue;
            }

            if ($parts[1] == $head) {
                return $parts[0];
            }
        }

        return null;
    }

    private function filterKeys(array &$arr, array $keysBlacklist)
    {
        foreach ($arr as $k => $v) {
            foreach ($keysBlacklist as $regexp) {
                if (preg_match($regexp, $k)) {
                    $arr[$k] = '[Filtered]';
                    continue;
                }
                if (is_array($v)) {
                    $this->filterKeys($arr[$k], $keysBlacklist);
                }
            }
        }
    }
}
