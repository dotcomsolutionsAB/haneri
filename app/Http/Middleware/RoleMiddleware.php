<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        \Log::info('Authenticated role: ' . auth()->user()->role);
        \Log::info('Allowed roles: ', $roles);

        \Log::info('Request Route: ' . $request->path());
        \Log::info('Authenticated User: ', ['id' => auth()->id(), 'role' => auth()->user()->role]);
        \Log::info('Allowed Roles: ', $roles);

        if (auth()->check() && in_array(auth()->user()->role, $roles)) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }
}
