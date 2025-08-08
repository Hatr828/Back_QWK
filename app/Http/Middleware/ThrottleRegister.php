<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

class ThrottleRegister
{
    protected RateLimiter $limiter;
    protected int $maxAttempts = 3;     
    protected int $decaySeconds = 60;   

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function handle(Request $request, Closure $next)
    {
        $key = $this->key($request);

        if ($this->limiter->tooManyAttempts($key, $this->maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            return response()->json([
                'message' => 'Too Many Requests',
                'retry_after' => $retryAfter
            ], 429)->withHeaders([
                'Retry-After'          => $retryAfter,
                'X-RateLimit-Limit'    => $this->maxAttempts,
                'X-RateLimit-Remaining'=> 0,
            ]);
        }

        $this->limiter->hit($key, $this->decaySeconds);

        $response = $next($request);

        $remaining = max(0, $this->maxAttempts - $this->limiter->attempts($key));
        return $response->withHeaders([
            'X-RateLimit-Limit'     => $this->maxAttempts,
            'X-RateLimit-Remaining' => $remaining,
        ]);
    }

    protected function key(Request $request): string
    {
        return 'throttle:register:' . $request->ip() . ':' . $request->path();
    }
}
