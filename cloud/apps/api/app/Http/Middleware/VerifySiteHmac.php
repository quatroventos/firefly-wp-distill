<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class VerifySiteHmac
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('X-FFD-Key', '');
        $ts = (string) $request->header('X-FFD-Timestamp', '');
        $sig = (string) $request->header('X-FFD-Signature', '');

        if ($key === '' || $ts === '' || $sig === '' || !ctype_digit($ts)) {
            return response()->json(['message' => 'HMAC ausente'], 401);
        }
        if (abs(time() - (int) $ts) > 300) {
            return response()->json(['message' => 'timestamp fora da janela'], 401);
        }

        $site = Site::query()->where('site_key_id', $key)->where('active', true)->first();
        if (!$site) {
            return response()->json(['message' => 'site desconhecido'], 401);
        }

        $path = $request->getPathInfo();
        $body = $request->getContent();
        $payload = $ts . "\n" . strtoupper($request->getMethod()) . "\n" . $path . "\n" . hash('sha256', $body);
        $expected = hash_hmac('sha256', $payload, $site->hmac_secret);
        if (!hash_equals($expected, $sig)) {
            return response()->json(['message' => 'assinatura inválida'], 401);
        }

        $replayKey = 'ffd_replay_' . hash('sha256', $sig);
        if (!Cache::add($replayKey, 1, 300)) {
            return response()->json(['message' => 'replay'], 401);
        }

        $request->attributes->set('ffd_site', $site);
        return $next($request);
    }
}
