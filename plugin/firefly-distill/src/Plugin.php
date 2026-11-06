<?php

namespace FireflyDistill;

final class Plugin
{
    public function boot(): void
    {
        load_plugin_textdomain('firefly-distill', false, dirname(plugin_basename(FFD_FILE)) . '/languages');

        (new Install\Schema())->maybeUpgrade();
        (new Rest\Routes())->register();
        (new Crawl\BypassToken())->register();
        (new Serving\SeoGuards())->register();
        (new Queue\Hooks())->register();

        add_action('ffd_flush_host_queue', [Queue\Enqueuer::class, 'flushHost'], 10, 1);

        if (is_admin()) {
            (new Admin\Menu())->register();
        }
    }
}
