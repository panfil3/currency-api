<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use App\Infrastructure\RateLimiting\RedisRateLimiter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RateLimitMiddleware
{
    private const USER_LIMIT = 500;
    private const IP_LIMIT = 1000;
    private const WINDOW = 60;

    public function __construct(
        private RedisRateLimiter $rateLimiter
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->id ?? 'guest';
        $ip = $request->ip();

        $userKey = "user:{$userId}";
        if (!$this->rateLimiter->attempt($userKey, self::USER_LIMIT, self::WINDOW)) {
            return response()->json([
                'error' => 'Rate limit exceeded for user',
                'retry_after' => self::WINDOW
            ], 429);
        }

        $ipKey = "ip:{$ip}";
        if (!$this->rateLimiter->attempt($ipKey, self::IP_LIMIT, self::WINDOW)) {
            return response()->json([
                'error' => 'Rate limit exceeded for IP',
                'retry_after' => self::WINDOW
            ], 429);
        }

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit-User', (string) self::USER_LIMIT);
        $response->headers->set('X-RateLimit-Remaining-User',
            (string) $this->rateLimiter->remaining($userKey, self::USER_LIMIT));

        $response->headers->set('X-RateLimit-Limit-IP', (string) self::IP_LIMIT);
        $response->headers->set('X-RateLimit-Remaining-IP',
            (string) $this->rateLimiter->remaining($ipKey, self::IP_LIMIT));

        return $response;
    }
}