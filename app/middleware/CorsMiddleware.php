<?php

namespace app\middleware;

class CorsMiddleware
{
    private array $allowedOrigins = [
        'http://localhost:5173',
        'https://seo-git-fixed-version-adeizaonimisi377-gmailcoms-projects.vercel.app/'
    ];

    public function handle($request, \Closure $next)
    {
        $origin = $request->header('origin');

        $allowOrigin = in_array($origin, $this->allowedOrigins)
            ? $origin
            : null;

        // Handle preflight FIRST
        if ($request->method() === 'OPTIONS') {
            $response = response('', 204);

            if ($allowOrigin) {
                $response->header('Access-Control-Allow-Origin', $allowOrigin);
                $response->header('Vary', 'Origin');
            }

            return $response
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        $response = $next($request);

        if ($allowOrigin) {
            $response->header('Access-Control-Allow-Origin', $allowOrigin);
            $response->header('Vary', 'Origin');
        }

        return $response
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->header('Access-Control-Allow-Credentials', 'true');
    }
}