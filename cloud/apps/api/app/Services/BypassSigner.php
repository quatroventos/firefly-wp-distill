<?php

namespace App\Services;

use App\Models\Site;

final class BypassSigner
{
    public function sign(Site $site, string $host, string $path, int $ttl = 7200): array
    {
        $exp = (string) (time() + min($ttl, 7200));
        $path = str_starts_with($path, '/') ? $path : '/' . $path;
        if (!str_ends_with($path, '/')) {
            $path .= '/';
        }
        $sig = hash_hmac('sha256', $host . "\n" . $path . "\n" . $exp, $site->hmac_secret);
        return ['ff_sig' => $sig, 'ff_exp' => $exp];
    }
}
