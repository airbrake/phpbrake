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
        switch ($code) {
            case E_NOTICE:
            case E_USER_NOTICE:
                $exc = new Errors\Notice($message, debug_backtrace());
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $exc = new Errors\Warning($message, debug_backtrace());
                break;
            case E_ERROR:
            case E_CORE_ERROR:
            case E_RECOVERABLE_ERROR:
                $exc = new Errors\Fatal($message, debug_backtrace());
                break;
            case E_USER_ERROR:
            default:
                $exc = new Errors\Error($message, debug_backtrace());
        }
        $this->notifier->notify($exc);
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
        if ($error['type'] & error_reporting() === 0) {
            return;
        }
        $exc = new Errors\Fatal($error['message'], debug_backtrace());
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
