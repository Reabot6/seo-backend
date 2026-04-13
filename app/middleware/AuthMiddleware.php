<?php
namespace app\middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Db;

class AuthMiddleware
{
    // Routes that don't need authentication
    private $publicRoutes = [
        'api/auth/login',
        'api/auth/verify-2fa',
        'api/settings/language',
    ];

    public function handle($request, \Closure $next)
    {
        $path = $request->pathinfo();

        // Allow public routes
        foreach ($this->publicRoutes as $route) {
            if (str_starts_with($path, $route)) {
                return $next($request);
            }
        }

        // Allow OPTIONS requests (CORS preflight)
        if ($request->method() === 'OPTIONS') {
            return $next($request);
        }

        // Get token from header
        $token = $request->header('Authorization');

        if (!$token) {
            return json([
                'status'  => 'error',
                'message' => 'Unauthorized — no token provided',
            ], 401);
        }

        $token = str_replace('Bearer ', '', $token);

        try {
            $secret  = 'seoforge_jwt_secret_key_2026_automation_system_secure_key_xyz';
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            // Reject temp tokens
            if ($decoded->tmp) {
                return json([
                    'status'  => 'error',
                    'message' => 'Unauthorized — temp token not allowed',
                ], 401);
            }

            // Attach user to request
            $user = Db::table('admin_users')
                ->where('id', $decoded->sub)
                ->where('status', 'active')
                ->find();

            if (!$user) {
                return json([
                    'status'  => 'error',
                    'message' => 'Unauthorized — user not found',
                ], 401);
            }

            // Make user available to controllers
            $request->user = $user;

        } catch (\Exception $e) {
            return json([
                'status'  => 'error',
                'message' => 'Unauthorized — invalid or expired token',
            ], 401);
        }

        return $next($request);
    }
}