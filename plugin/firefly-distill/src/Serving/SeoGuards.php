<?php

namespace FireflyDistill\Serving;

use FireflyDistill\Hosts\HostRepository;

final class SeoGuards
{
    public function register(): void
    {
        add_filter('wpseo_exclude_from_sitemap_by_post_ids', [$this, 'excludePostIds']);
        add_filter('rank_math/sitemap/exclude_post_ids', [$this, 'excludePostIds']);
        add_filter('wp_sitemaps_posts_query_args', [$this, 'coreSitemapArgs'], 10, 2);
        add_filter('wpseo_canonical', [$this, 'canonical']);
        add_filter('rank_math/frontend/canonical', [$this, 'canonical']);
        add_filter('get_canonical_url', [$this, 'canonicalForPost'], 10, 2);
        add_action('wp_head', [$this, 'maybePrintCanonical'], 1);
    }

    /**
     * @param int[] $ids
     * @return int[]
     */
    public function excludePostIds($ids): array
    {
        $ids = is_array($ids) ? $ids : [];
        return array_values(array_unique(array_merge($ids, (new HostRepository())->mappedPostIds())));
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function coreSitemapArgs(array $args, string $postType): array
    {
        $ids = (new HostRepository())->mappedPostIds();
        if ($ids === []) {
            return $args;
        }
        $args['post__not_in'] = array_values(array_unique(array_merge(
            isset($args['post__not_in']) && is_array($args['post__not_in']) ? $args['post__not_in'] : [],
            $ids
        )));
        return $args;
    }

    public function canonical(?string $canonical): ?string
    {
        if (!is_singular()) {
            return $canonical;
        }
        $public = $this->publicUrlFor((int) get_queried_object_id());
        return $public ?? $canonical;
    }

    public function canonicalForPost(string $canonical, \WP_Post $post): string
    {
        return $this->publicUrlFor((int) $post->ID) ?? $canonical;
    }

    public function maybePrintCanonical(): void
    {
        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')) {
            return;
        }
        if (!is_singular()) {
            return;
        }
        $url = $this->publicUrlFor((int) get_queried_object_id());
        if ($url) {
            echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";
        }
    }

    public function publicUrlFor(int $postId): ?string
    {
        if ($postId <= 0) {
            return null;
        }
        $repo = new HostRepository();
        $hosts = $repo->findByPost($postId);
        foreach ($hosts as $host) {
            $scheme = $host->https ? 'https' : 'http';
            if ((int) $host->post_id === $postId && (int) $host->is_primary !== 1) {
                return $scheme . '://' . $host->host . '/';
            }
            foreach ($host->routes as $route) {
                if ((int) $route->post_id === $postId) {
                    return $scheme . '://' . $host->host . rtrim($route->path, '/') . '/';
                }
            }
        }
        return null;
    }
}
