<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExceptCsrf
{
    public function handle(Request $request, Closure $next): Response
    {
        $exceptRoutes = [
            'mortality/custom-fields/*',
        ];

        foreach ($exceptRoutes as $route) {
            if ($request->is($route)) {
                return $next($request);
            }
        }

        return $next($request);
    }
}
