<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Acceso denegado. Se requiere rol de administrador.',
                'user_info' => [
                    'is_authenticated' => !is_null($request->user()),
                    'user' => $request->user() ? [
                        'id' => $request->user()->id,
                        'email' => $request->user()->email,
                        'role' => $request->user()->role
                    ] : null
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}