<?php

namespace FireflyDistill\Queue;

final class Debounce
{
    public const SECONDS = 60;

    public static function schedule(string $host): void
    {
        $key = 'ffd_debounce_' . md5($host);
        if (get_transient($key)) {
            return;
        }
        set_transient($key, 1, self::SECONDS);
        $hook = 'ffd_flush_host_queue';
        $next = wp_next_scheduled($hook, [$host]);
        if ($next) {
            return;
        }
        wp_schedule_single_event(time() + self::SECONDS, $hook, [$host]);
    }
}
