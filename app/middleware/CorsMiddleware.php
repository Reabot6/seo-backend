<?php
namespace app\middleware;

class CorsMiddleware
{
    public function handle($request, \Closure $next)
    {
        return $next($request);
    }
}