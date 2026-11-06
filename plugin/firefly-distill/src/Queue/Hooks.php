<?php

namespace FireflyDistill\Queue;

final class Hooks
{
    public function register(): void
    {
        add_action('save_post', [$this, 'onSave'], 20, 3);
        add_action('before_delete_post', [$this, 'onDelete']);
        add_action('transition_post_status', [$this, 'onStatus'], 10, 3);
        add_action('wp_update_nav_menu', [$this, 'onMenu']);
        add_action('customize_save_after', [$this, 'onCustomizer']);
    }

    public function onSave(int $postId, \WP_Post $post, bool $update): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }
        if ($post->post_status !== 'publish') {
            return;
        }
        (new Enqueuer())->enqueuePost($postId, 'save_post');
    }

    public function onDelete(int $postId): void
    {
        if (wp_is_post_revision($postId)) {
            return;
        }
        (new Enqueuer())->enqueuePost($postId, 'delete');
    }

    public function onStatus(string $new, string $old, \WP_Post $post): void
    {
        if ($new === $old) {
            return;
        }
        (new Enqueuer())->enqueuePost((int) $post->ID, 'status');
    }

    public function onMenu(): void
    {
        (new Enqueuer())->enqueueAllHosts('menu');
    }

    public function onCustomizer(): void
    {
        (new Enqueuer())->enqueueAllHosts('customizer');
    }
}
