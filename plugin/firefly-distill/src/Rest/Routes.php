<?php

namespace FireflyDistill\Rest;

final class Routes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void
    {
        $ns = FFD_REST_NS;

        register_rest_route($ns, '/status', [
            'methods' => 'GET',
            'callback' => [StatusController::class, 'show'],
            'permission_callback' => [Auth::class, 'adminOrHmac'],
        ]);

        register_rest_route($ns, '/urls', [
            'methods' => 'GET',
            'callback' => [UrlsController::class, 'index'],
            'permission_callback' => [Auth::class, 'adminOrHmac'],
        ]);

        register_rest_route($ns, '/hosts', [
            [
                'methods' => 'GET',
                'callback' => [HostsController::class, 'index'],
                'permission_callback' => [Auth::class, 'adminOrHmac'],
            ],
            [
                'methods' => 'POST',
                'callback' => [HostsController::class, 'store'],
                'permission_callback' => [Auth::class, 'adminOnly'],
            ],
        ]);

        register_rest_route($ns, '/hosts/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [HostsController::class, 'show'],
                'permission_callback' => [Auth::class, 'adminOrHmac'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [HostsController::class, 'update'],
                'permission_callback' => [Auth::class, 'adminOnly'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [HostsController::class, 'destroy'],
                'permission_callback' => [Auth::class, 'adminOnly'],
            ],
        ]);

        register_rest_route($ns, '/hosts/import', [
            'methods' => 'POST',
            'callback' => [HostsController::class, 'import'],
            'permission_callback' => [Auth::class, 'adminOnly'],
        ]);

        register_rest_route($ns, '/hosts/search-posts', [
            'methods' => 'GET',
            'callback' => [HostsController::class, 'searchPosts'],
            'permission_callback' => [Auth::class, 'adminOnly'],
        ]);

        register_rest_route($ns, '/hosts/(?P<id>\d+)/rebuild', [
            'methods' => 'POST',
            'callback' => [HostsController::class, 'rebuild'],
            'permission_callback' => [Auth::class, 'adminOnly'],
        ]);

        register_rest_route($ns, '/hosts/(?P<id>\d+)/rollback', [
            'methods' => 'POST',
            'callback' => [HostsController::class, 'rollback'],
            'permission_callback' => [Auth::class, 'adminOnly'],
        ]);

        register_rest_route($ns, '/hosts/(?P<id>\d+)/test', [
            'methods' => 'POST',
            'callback' => [HostsController::class, 'testHost'],
            'permission_callback' => [Auth::class, 'adminOnly'],
        ]);

        register_rest_route($ns, '/queue', [
            'methods' => 'POST',
            'callback' => [QueueController::class, 'store'],
            'permission_callback' => [Auth::class, 'adminOrHmac'],
        ]);

        register_rest_route($ns, '/receive-build', [
            'methods' => 'POST',
            'callback' => [ReceiveBuildController::class, 'store'],
            'permission_callback' => [Auth::class, 'hmacAndJobToken'],
        ]);

        register_rest_route($ns, '/builds/(?P<host>[a-z0-9.-]+)/(?P<id>[A-Za-z0-9._-]+)/activate', [
            'methods' => 'POST',
            'callback' => [BuildsController::class, 'activate'],
            'permission_callback' => [Auth::class, 'adminOrHmac'],
        ]);

        register_rest_route($ns, '/serve', [
            'methods' => 'POST',
            'callback' => [ServeController::class, 'update'],
            'permission_callback' => [Auth::class, 'adminOnly'],
        ]);

        register_rest_route($ns, '/audits', [
            'methods' => 'GET',
            'callback' => [AuditsController::class, 'index'],
            'permission_callback' => [Auth::class, 'adminOnly'],
        ]);

        register_rest_route($ns, '/audits/detail', [
            'methods' => 'GET',
            'callback' => [AuditsController::class, 'detail'],
            'permission_callback' => [Auth::class, 'adminOnly'],
        ]);

        register_rest_route($ns, '/suggestions', [
            [
                'methods' => 'GET',
                'callback' => [SuggestionsController::class, 'index'],
                'permission_callback' => [Auth::class, 'adminOnly'],
            ],
            [
                'methods' => 'POST',
                'callback' => [SuggestionsController::class, 'store'],
                'permission_callback' => [Auth::class, 'adminOrHmac'],
            ],
        ]);

        register_rest_route($ns, '/suggestions/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [SuggestionsController::class, 'update'],
            'permission_callback' => [Auth::class, 'adminOnly'],
        ]);

        register_rest_route($ns, '/webhook', [
            'methods' => 'POST',
            'callback' => [WebhookController::class, 'handle'],
            'permission_callback' => [Auth::class, 'hmac'],
        ]);

        register_rest_route($ns, '/job-token', [
            'methods' => 'POST',
            'callback' => [ReceiveBuildController::class, 'issueToken'],
            'permission_callback' => [Auth::class, 'hmac'],
        ]);
    }
}
