<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class RestrictToAllowedIps
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<int, string> $allowedIps */
        $allowedIps = config('intercall.http_fallback.allowed_ips', []);

        if ($allowedIps === []) {
            return $next($request);
        }

        $clientIp = $request->ip();

        if ($clientIp !== null && IpUtils::checkIp($clientIp, $allowedIps)) {
            return $next($request);
        }

        logger()->debug('[Intercall HTTP] Request rejected from IP outside allowed list', [
            'ip' => $clientIp,
            'path' => $request->path(),
        ]);

        return response()->json([
            'success' => false,
            'error' => 'Forbidden',
        ], 403);
    }
}
