<?php

namespace Tests\Feature;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class VerifySiteHmacTest extends TestCase
{
    use RefreshDatabase;

    public function testHealthIsPublic(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertJson(['ok' => true]);
    }

    public function testEnqueueRejectsMissingHmac(): void
    {
        $this->postJson('/api/v1/sites/x/enqueue', ['items' => []])->assertStatus(401);
    }

    public function testEnqueueAcceptsValidHmac(): void
    {
        $site = Site::query()->create([
            'home_url' => 'https://incavel.example.test',
            'site_key_id' => 'ffd_testkey',
            'hmac_secret' => 'test-secret',
            'delivery' => 'rest',
            'active' => true,
        ]);
        $path = '/api/v1/sites/ffd_testkey/enqueue';
        $body = json_encode(['items' => []], JSON_THROW_ON_ERROR);
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts . "\nPOST\n" . $path . "\n" . hash('sha256', $body), 'test-secret');

        $this->call('POST', $path, [], [], [], [
            'HTTP_X_FFD_KEY' => $site->site_key_id,
            'HTTP_X_FFD_TIMESTAMP' => $ts,
            'HTTP_X_FFD_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $body)->assertStatus(422);
    }
}
