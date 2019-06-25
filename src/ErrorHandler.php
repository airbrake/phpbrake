<?php

namespace Airbrake;

/**
 * Handlers for errors, exceptions and shutdowns.
 */
class ErrorHandler
{
    /**
     * @var Notifier
     */
    private $notifier;

    /**
     * @var array
     */
    private $lastError;

    /**
     * @param Notifier $notifier Notifier instance
     */
    public function __construct(\Airbrake\Notifier $notifier)
    {
        $this->notifier = $notifier;
    }

    /**
     * PHP error handler that notifies Airbrake about errors. Should be used
     * with set_error_handler.
     */
    public function onError($code, $message, $file, $line)
    {
        $this->lastError = [
            'message' => $message,
            'file' => $file,
            'line' => $line
        ];

        $trace = debug_backtrace();
        if (count($trace) > 0 && !isset($trace[0]['file'])) {
            array_shift($trace);
        }

        $exc = new Errors\Base($message, $trace);
        $notice = $this->notifier->buildNotice($exc);

        $notice['errors'][0]['type'] = $this->errnoType($code);
        $severity = $this->errnoSeverity($code);
        if ($severity !== '') {
            $notice['context']['severity'] = $severity;
        }

        $this->notifier->sendNotice($notice);

        return false;
    }

    /**
     * PHP exception handler that notifies Airbrake about exceptions. Should be
     * used with set_exception_handler.
     */
    public function onException($exc)
    {
        $this->notifier->notify($exc);
    }

    /**
     * PHP shutdown handler that notifies Airbrake about shutdown. Should be
     * used with register_shutdown_function.
     */
    public function onShutdown()
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }
        if (($error['type'] & error_reporting()) === 0) {
            return;
        }
        if ($this->lastError !== null &&
            $error['message'] === $this->lastError['message'] &&
            $error['file'] === $this->lastError['file'] &&
            $error['line'] === $this->lastError['line']) {
            return;
        }

        $trace = [[
            'file' => $error['file'],
            'line' => $error['line'],
        ]];
        $exc = new Errors\Base($error['message'], $trace);
        $notice = $this->notifier->buildNotice($exc);
        $notice['errors'][0]['type'] = 'shutdown';
        $this->notifier->sendNotice($notice);
    }

    /**
     * Registers error, exception and shutdown handlers.
     */
    public function register()
    {
        set_error_handler([$this, 'onError'], error_reporting());
        set_exception_handler([$this, 'onException']);
        register_shutdown_function([$this, 'onShutdown']);
    }

    protected function errnoType($code)
    {
        switch ($code) {
            case E_ERROR:
                return 'E_ERROR';
            case E_WARNING:
                return 'E_WARNING';
            case E_PARSE:
                return 'E_PARSE';
            case E_NOTICE:
                return 'E_NOTICE';
            case E_CORE_ERROR:
                return 'E_CORE_ERROR';
            case E_CORE_WARNING:
                return 'E_CORE_WARNING';
            case E_COMPILE_ERROR:
                return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING:
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR:
                return 'E_USER_ERROR';
            case E_USER_WARNING:
                return 'E_USER_WARNING';
            case E_USER_NOTICE:
                return 'E_USER_NOTICE';
            case E_STRICT:
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR:
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED:
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED:
                return 'E_USER_DEPRECATED';
            case E_ALL:
                return 'E_ALL';
        }
        return 'E_UNKNOWN';
    }

    protected function errnoSeverity($code)
    {
        switch ($code) {
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'notice';
            case E_WARNING:
            case E_USER_WARNING:
                return 'warning';
        }
        return '';
    }
}
