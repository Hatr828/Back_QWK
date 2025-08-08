<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $origin = $request->headers->get('Origin');
        $allowed = [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
        ];

        $headers = [
            'Access-Control-Allow-Origin'      => in_array($origin, $allowed, true) ? $origin : 'http://localhost:5173',
            'Vary'                              => 'Origin',
            'Access-Control-Allow-Credentials' => 'false',
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Max-Age'           => '600',
        ];

        if ($request->getMethod() === 'OPTIONS') {
            return response('', 204)->withHeaders($headers);
        }

        $response = $next($request);
        foreach ($headers as $k => $v) {
            $response->headers->set($k, $v);
        }
        return $response;
    }
}
