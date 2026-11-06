<?php

namespace FireflyDistill\Rest;

use FireflyDistill\Hosts\HostValidator;
use FireflyDistill\Serving\Publisher;
use WP_REST_Request;
use WP_REST_Response;

final class BuildsController
{
    public static function activate(WP_REST_Request $request): WP_REST_Response
    {
        $host = HostValidator::normalize((string) $request['host']);
        $id = sanitize_file_name((string) $request['id']);
        try {
            (new Publisher())->activate($host, $id);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 422);
        }
        return new WP_REST_Response(['ok' => true]);
    }
}
