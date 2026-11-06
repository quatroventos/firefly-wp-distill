<?php

namespace FireflyDistill\Queue;

use FireflyDistill\Security\Hmac;

final class CloudClient
{
    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,status:int,body:array<string,mixed>,error:?string}
     */
    public function request(string $method, string $path, array $payload = []): array
    {
        $settings = get_option('ffd_settings', []);
        $base = rtrim((string) ($settings['cloud_url'] ?? ''), '/');
        $key = (string) ($settings['site_key_id'] ?? '');
        $secret = Hmac::secret();
        if ($base === '' || $key === '' || $secret === '') {
            return ['ok' => false, 'status' => 0, 'body' => [], 'error' => 'conexão incompleta'];
        }
        $path = '/' . ltrim($path, '/');
        $url = $base . $path;
        $body = $payload === [] && strtoupper($method) === 'GET' ? '' : wp_json_encode($payload);
        $ts = (string) time();
        $sig = Hmac::sign($secret, $ts, $method, $path, (string) $body);
        $args = [
            'method' => strtoupper($method),
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                Hmac::HEADER_KEY => $key,
                Hmac::HEADER_TS => $ts,
                Hmac::HEADER_SIG => $sig,
            ],
        ];
        if ($body !== '') {
            $args['body'] = $body;
        }
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return ['ok' => false, 'status' => 0, 'body' => [], 'error' => $response->get_error_message()];
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        $decoded = is_array($decoded) ? $decoded : [];
        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $decoded,
            'error' => $status >= 300 ? (string) ($decoded['message'] ?? 'erro HTTP ' . $status) : null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    public function enqueue(array $items): array
    {
        $settings = get_option('ffd_settings', []);
        $key = (string) ($settings['site_key_id'] ?? '');
        return $this->request('POST', '/api/v1/sites/' . rawurlencode($key) . '/enqueue', ['items' => $items]);
    }

    /**
     * @param list<array<string,mixed>> $hosts
     */
    public function syncHosts(array $hosts): array
    {
        $settings = get_option('ffd_settings', []);
        $key = (string) ($settings['site_key_id'] ?? '');
        return $this->request('PUT', '/api/v1/sites/' . rawurlencode($key) . '/hosts', [
            'hosts' => $hosts,
            'home_url' => home_url('/'),
            'plugin_rest_url' => rest_url('firefly-distill/v1'),
        ]);
    }

    public function health(): array
    {
        $settings = get_option('ffd_settings', []);
        $base = rtrim((string) ($settings['cloud_url'] ?? ''), '/');
        if ($base === '') {
            return ['ok' => false, 'status' => 0, 'body' => [], 'error' => 'cloud_url vazio'];
        }
        $response = wp_remote_get($base . '/api/v1/health', ['timeout' => 10]);
        if (is_wp_error($response)) {
            return ['ok' => false, 'status' => 0, 'body' => [], 'error' => $response->get_error_message()];
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        return [
            'ok' => $status === 200,
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : [],
            'error' => $status === 200 ? null : 'health falhou',
        ];
    }
}
