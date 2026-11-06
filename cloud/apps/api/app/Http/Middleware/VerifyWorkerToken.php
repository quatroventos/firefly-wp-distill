<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyWorkerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        if ($token === '') {
            $token = (string) $request->header('X-FFD-Worker-Token', '');
        }
        $expected = (string) config('ffd.worker_token');
        if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
            return response()->json(['message' => 'worker token inválido'], 401);
        }
        return $next($request);
    }
}
