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
    private static $count = 0;

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
     * @var number
     */
    private $rateLimitReset;

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
        ], $opt);

        if (!empty($opt['rootDirectory'])) {
            $this->addFilter(function ($notice) {
                return $this->rootDirectoryFilter($notice);
            });
        }

        $this->httpClient = $this->newHTTPClient();
        $this->noticesURL = $this->buildNoticesURL();

        if (self::$count === 0) {
            Instance::set($this);
        }
        self::$count++;
    }

    private function newHTTPClient()
    {
        if (isset($this->opt['httpClient'])) {
            if ($this->opt['httpClient'] instanceof GuzzleHttp\ClientInterface) {
                return $this->opt['httpClient'];
            }
            throw new Exception('phpbrake: httpClient must implement GuzzleHttp\ClientInterface');
        }
        return new Client(['timeout' => 5]);
    }

    /**
     * Appends filter to the list.
     *
     * Filter is a callback that accepts notice. Filter can modify passed
     * notice or return null if notice must be ignored.
     *
     * @param callable $filter Filter callback
     */
    public function addFilter($filter)
    {
        $this->filters[] = $filter;
    }

    private function backtrace($exc)
    {
        $backtrace = [];
        $backtrace[] = [
            'file' => $exc->getFile(),
            'line' => $exc->getLine(),
            'function' => ''
        ];
        $trace = $exc->getTrace();
        foreach ($trace as $frame) {
            $func = $frame['function'];
            if (isset($frame['class']) && isset($frame['type'])) {
                $func = $frame['class'] . $frame['type'] . $func;
            }
            if (count($backtrace) > 0) {
                $backtrace[count($backtrace) - 1]['function'] = $func;
            }

            $backtrace[] = [
                'file' => isset($frame['file']) ? $frame['file'] : '',
                'line' => isset($frame['line']) ? $frame['line'] : 0,
                'function' => ''
            ];
        }
        return $backtrace;
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

        $context = [
            'notifier' => [
                'name' => 'phpbrake',
                'version' => '0.4.1',
                'url' => 'https://github.com/airbrake/phpbrake',
            ],
            'os' => php_uname(),
            'language' => 'php ' . phpversion(),
        ];
        if (!empty($this->opt['appVersion'])) {
            $context['version'] = $this->opt['appVersion'];
        }
        if (!empty($this->opt['environment'])) {
            $context['environment'] = $this->opt['environment'];
        }
        if (($hostname = gethostname()) !== false) {
            $context['hostname'] = $hostname;
        }
        if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
            $scheme = isset($_SERVER['HTTPS']) ? 'https' : 'http';
            $context['url'] = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $context['userAgent'] = $_SERVER['HTTP_USER_AGENT'];
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
            '%s/api/v3/projects/%d/notices?key=%s',
            $schemeAndHost,
            $this->opt['projectId'],
            $this->opt['projectKey']
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
                $frame['file'] = preg_replace("~^$projectRoot~", '[PROJECT_ROOT]', $frame['file']);
            }
        }
        return $notice;
    }
}
