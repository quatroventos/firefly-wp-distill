<?php

namespace FireflyDistill\Rest;

use FireflyDistill\Hosts\HostValidator;
use FireflyDistill\Queue\Enqueuer;
use WP_REST_Request;
use WP_REST_Response;

final class QueueController
{
    public static function store(WP_REST_Request $request): WP_REST_Response
    {
        $host = HostValidator::normalize((string) $request->get_param('host'));
        $path = HostValidator::normalizePath((string) ($request->get_param('path') ?? '/'));
        $postId = (int) $request->get_param('post_id');
        $reason = sanitize_key((string) ($request->get_param('reason') ?? 'manual'));
        $url = (string) ($request->get_param('url') ?? '');
        if (!HostValidator::isValid($host)) {
            return new WP_REST_Response(['message' => 'host inválido'], 422);
        }
        (new Enqueuer())->enqueueHost($host, $path, $url !== '' ? $url : 'https://' . $host . $path, $postId, $reason);
        return new WP_REST_Response(['ok' => true]);
    }
}
