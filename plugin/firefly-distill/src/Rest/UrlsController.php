<?php

namespace FireflyDistill\Rest;

use FireflyDistill\Crawl\UrlDiscoverer;
use WP_REST_Request;
use WP_REST_Response;

final class UrlsController
{
    public static function index(WP_REST_Request $request): WP_REST_Response
    {
        $host = $request->get_param('host');
        $host = is_string($host) && $host !== '' ? $host : null;
        return new WP_REST_Response([
            'urls' => (new UrlDiscoverer())->forHost($host),
        ]);
    }
}
