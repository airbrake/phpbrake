<?php

namespace Airbrake;

/**
 * Global Notifier instance.
 */
class Instance
{
    /**
     * @var Notifier
     */
    private static $notifier;

    /**
     * Sets notifier instance.
     *
     * @param Notifier $notifier Notifier instance
     */
    public static function set($notifier)
    {
        self::$notifier = $notifier;
    }

    /**
     * Alias for Notifier::buildNotice.
     */
    public static function buildNotice($exc)
    {
        return self::$notifier->buildNotice($exc);
    }

    /**
     * Alias for Notifier::sendNotice.
     */
    public static function sendNotice($notice)
    {
        return self::$notifier->sendNotice($notice);
    }

    /**
     * Alias for Notifier::notify.
     */
    public static function notify($exc)
    {
        return self::$notifier->notify($exc);
    }

    /**
     * Alias for Notifier::sendNotice.
     */
    public static function sendNoticeAsync($notice)
    {
        return self::$notifier->sendNoticeAsync($notice);
    }

    /**
     * Alias for Notifier::notifyAsync.
     */
    public static function notifyAsync($exc)
    {
        return self::$notifier->notifyAsync($exc);
    }
}
