<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    // public function handle(Request $request, Closure $next): Response
    // {
    //     return $next($request);
    // }

    // public function handle(Request $request, Closure $next)
    // {
    //     if ($request->isMethod('OPTIONS')) {
    //         $response = response('', 200);
    //     } else {
    //         $response = $next($request);
    //     }

    //     // Set CORS headers for the response
    //     $response->headers->set('Access-Control-Allow-Origin', 'https://haneri.ongoingsites.xyz'); // Your frontend domain
    //     $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    //     $response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, X-Requested-With');
    //     $response->headers->set('Access-Control-Allow-Credentials', 'true');

    //     return $response;
    // }

    // public function handle(Request $request, Closure $next): Response
    // {
    //     $allowedOrigins = ['https://haneri.ongoingsites.xyz']; // Add frontend domain

    //     $origin = $request->headers->get('Origin');

    //     if (in_array($origin, $allowedOrigins)) {
    //         $response = $next($request);
    //         $response->headers->set('Access-Control-Allow-Origin', $origin);
    //         $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    //         $response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, X-Requested-With');
    //         $response->headers->set('Access-Control-Allow-Credentials', 'true');
    //     } else {
    //         $response = $next($request);
    //     }

    //     return $response;
    // }

    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = ['https://haneri.ongoingsites.xyz']; // Your frontend domain
        $origin = $request->headers->get('Origin');

        // Allow only allowed origins
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
        }

        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-Requested-With, X-CSRF-TOKEN");
        header("Access-Control-Allow-Credentials: true");

        // Handle preflight requests
        if ($request->isMethod('OPTIONS')) {
            return response()->json('Preflight OK', 200);
        }

        return $next($request);
    }

}
