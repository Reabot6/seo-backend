namespace app\middleware;

class CorsMiddleware
{
    public function handle($request, \Closure $next)
    {
        // ALWAYS fixed origin (DO NOT use dynamic origin in dev)
        $origin = '*';

        // Preflight request
        if ($request->method() === 'OPTIONS') {
            return response('', 204)->header([
                'Access-Control-Allow-Origin'      => $origin,
                'Access-Control-Allow-Methods'     => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }

        $response = $next($request);

        return $response->header([
            'Access-Control-Allow-Origin'      => $origin,
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With',
            'Access-Control-Allow-Credentials' => 'true',
        ]);
    }
}