<?php

namespace App\Services;

use App\Models\Job;
use App\Models\Site;
use Illuminate\Support\Facades\Http;

final class DeliveryService
{
    public function signPlugin(Site $site, string $method, string $path, string $body): array
    {
        $ts = (string) time();
        $payload = $ts . "\n" . strtoupper($method) . "\n" . $path . "\n" . hash('sha256', $body);
        return [
            'X-FFD-Key' => $site->site_key_id,
            'X-FFD-Timestamp' => $ts,
            'X-FFD-Signature' => hash_hmac('sha256', $payload, $site->hmac_secret),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public function webhook(Site $site, array $payload): void
    {
        $base = rtrim((string) $site->plugin_rest_url, '/');
        if ($base === '') {
            return;
        }
        $path = parse_url($base . '/webhook', PHP_URL_PATH) ?: '/wp-json/firefly-distill/v1/webhook';
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        Http::withHeaders($this->signPlugin($site, 'POST', $path, $body))
            ->timeout(20)
            ->withBody($body, 'application/json')
            ->post($base . '/webhook');
    }

    public function issueJobToken(Site $site, Job $job, string $host, string $buildId): ?string
    {
        $base = rtrim((string) $site->plugin_rest_url, '/');
        if ($base === '') {
            return $job->job_token;
        }
        $path = parse_url($base . '/job-token', PHP_URL_PATH) ?: '/wp-json/firefly-distill/v1/job-token';
        $payload = ['host' => $host, 'build_id' => $buildId, 'job_id' => $job->id];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $res = Http::withHeaders($this->signPlugin($site, 'POST', $path, $body))
            ->timeout(20)
            ->withBody($body, 'application/json')
            ->post($base . '/job-token');
        if (!$res->successful()) {
            return $job->job_token;
        }
        return $res->json('job_token') ?: $job->job_token;
    }
}
