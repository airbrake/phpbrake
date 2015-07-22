<?php
namespace Airbrake;

/**
 * Airbrake exception notifier.
 */
class Notifier
{
    /**
     * @var array
     */
    private $opt;

    /**
     * @var callable[]
     */
    private $filters = array();

    /**
     * @param array $opt Options such as projectId and projectKey
     */
    public function __construct($opt = array())
    {
        // TODO: test that projectId and projectKey exists
        $this->opt = array_merge($opt, array(
            'host' => 'api.airbrake.io',
        ));
    }

    /**
     * Appends filter to the list.
     *
     * Filter is a callback that accepts notice. Filter can modify passed
     * notice or return false if notice must be ignored.
     *
     * @param callable $filter Filter callback
     */
    public function addFilter($filter)
    {
        $this->filters[] = $filter;
    }

    /**
     * Builds Airbrake notice from exception.
     *
     * @param \Exception $exc Exception or class that implements similar interface.
     */
    public function buildNotice($exc)
    {
        $backtrace = array();
        $trace = $exc->getTrace();
        foreach ($trace as $frame) {
            $func = $frame['function'];
            if (isset($frame['class']) && isset($frame['type'])) {
                $func = $frame['class'] . $frame['type'] . $func;
            }
            $backtrace[] = array(
                'file' => isset($frame['file']) ? $frame['file'] : '',
                'line' => isset($frame['line']) ? $frame['line'] : 0,
                'function' => $func,
            );
        }

        if (count($backtrace) === 0) {
            $backtrace[] = array(
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
                'function' => '',
            );
        }

        $error = array(
            'type' => get_class($exc),
            'message' => $exc->getMessage(),
            'backtrace' => $backtrace,
        );

        $context = array(
            'os' => php_uname(),
            'language' => 'php ' . phpversion(),
        );
        if ($_SERVER['DOCUMENT_ROOT'] !== '') {
            $context['rootDir'] = $_SERVER['DOCUMENT_ROOT'];
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

        $notice = array(
            'notifier' => array(
                'name' => 'phpbrake',
                'version' => '0.0.4',
                'url' => 'https://github.com/airbrake/phpbrake',
            ),
            'errors' => array($error),
            'context' => $context,
            'environment' => $_SERVER,
        );
        if (!empty($_REQUEST)) {
            $notice['params'] = $_REQUEST;
        }
        if (!empty($_SESSION)) {
            $notice['session'] = $_SESSION;
        }

        return $notice;
    }

    /**
     * Posts data to the URL.
     */
    protected function postNotice($url, $data)
    {
        $options = array(
            'http' => array(
                'header'    => "Content-type: application/json\r\n",
                'method'    => 'POST',
                'content' => $data,
            ),
        );
        $context = stream_context_create($options);
        $respData = file_get_contents($url, false, $context);
        if ($respData === false) {
            // TODO: handle this? how?
            return 0;
        }
        $resp = json_decode($respData);
        return $resp->id;
    }

    /**
     * Sends notice to Airbrake.
     *
     * @param array $notice Airbrake notice
     */
    public function sendNotice($notice)
    {
        foreach ($this->filters as $filter) {
            $notice = $filter($notice);
            if ($notice === false) {
                // Ignore notice.
                return 0;
            }
        }

        $opt = $this->opt;
        $url = sprintf(
            'https://%s/api/v3/projects/%d/notices?key=%s',
            $opt['host'], $opt['projectId'], $opt['projectKey']
        );
        $data = json_encode($notice);
        return $this->postNotice($url, $data);
    }

    /**
     * Notifies Airbrake about exception.
     *
     * Under the hood notify is a shortcut for buildNotice and sendNotice.
     *
     * @param \Exception $exc Exception or class that implements similar interface.
     */
    public function notify($exc)
    {
        $notice = $this->buildNotice($exc);
        return $this->sendNotice($notice);
    }
}
