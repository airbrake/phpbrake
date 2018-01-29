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

        switch ($code) {
            case E_NOTICE:
            case E_USER_NOTICE:
                $exc = new Errors\Notice($message, $trace);
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $exc = new Errors\Warning($message, $trace);
                break;
            case E_ERROR:
            case E_CORE_ERROR:
            case E_RECOVERABLE_ERROR:
            case E_USER_ERROR:
                $exc = new Errors\Fatal($message, $trace);
                break;
            default:
                $exc = new Errors\Error($message, $trace);
                break;
        }
        $this->notifier->notify($exc);
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
        $exc = new Errors\Fatal($error['message'], $trace);
        $this->notifier->notify($exc);
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
}
