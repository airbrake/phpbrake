<?php
namespace Airbrake;

// Handlers for errors, exceptions and shutdowns.
class ErrorHandler {
  private $notifier;

  public function __construct($notifier) {
    $this->notifier = $notifier;
  }

  // Notifies Airbrake about errors.
  public function onError($code, $message, $file, $line) {
    switch ($code) {
      case E_NOTICE:
      case E_USER_NOTICE:
        $exc = new Errors\Notice($message, $file, $line, debug_backtrace());
        break;
      case E_WARNING:
      case E_USER_WARNING:
        $exc = new Errors\Warning($message, $file, $line, debug_backtrace());
        break;
      case E_ERROR:
      case E_CORE_ERROR:
      case E_RECOVERABLE_ERROR:
        $exc = new Errors\Fatal($message, $file, $line, debug_backtrace());
        break;
      case E_USER_ERROR:
      default:
        $exc = new Errors\Error($message, $file, $line, debug_backtrace());
    }
    $this->notifier->notify($exc);
  }

  // Notifies Airbrake about exceptions.
  public function onException($exc) {
    $this->notifier->notify($exc);
  }

  // Notifies Airbrake about shutdown.
  public function onShutdown() {
    $error = error_get_last();
    if ($error === null) {
      return;
    }
    if ($error['type'] & error_reporting() === 0) {
     return;
    }
    $exc = new Errors\Fatal($error['message'], $error['file'], $error['line']);
    $this->notifier->notify($exc);
  }

  // Registers error, exception and shutdown handler.
  public function register() {
    set_error_handler(array($this, 'onError'), error_reporting());
    set_exception_handler(array($this, 'onException'));
    register_shutdown_function(array($this, 'onShutdown'));
  }
}
