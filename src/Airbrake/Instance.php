<?php
namespace Airbrake;

// Global Notifier instance.
class Instance {
  private static $notifier;

  // Sets notifier instance.
  public static function set($notifier) {
    self::$notifier = $notifier;
  }

  // Alias for Notifier::buildNotice.
  public function buildNotice($exc) {
    return self::$notifier->buildNotice($exc);
  }

  // Alias for Notifier::sendNotice.
  public static function sendNotice($notice) {
    return self::$notifier->sendNotice($notice);
  }

  // Alias for Notifier::notify.
  public static function notify($exc) {
    return self::$notifier->notify($exc);
  }
}
