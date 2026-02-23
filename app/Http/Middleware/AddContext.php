<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class AddContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Context::add('url', sprintf('%s|%s', $request->method(), $request->url()));
        Context::add('request_id', app('request_id'));
        Context::add('ip', $request->ip());

        $response = $next($request);
        $response->headers->set('X-Request-Id', Context::get('request_id'));

        return $response;
    }
}
