<?php

namespace App\Http\Middleware;

use App\Exceptions\BusinessException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     *
     * @throws BusinessException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('api')->check()) {
            return $next($request);
        }

        $user = auth('api')->user();

        if ($user && ! $user->is_active) {
            auth('api')->logout();

            throw new BusinessException('Your account has been disabled', 403);
        }

        return $next($request);
    }
}
