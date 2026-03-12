<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->activo) {
            // Revocar el token que se usó en esta petición
            $user->currentAccessToken()->delete();

            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta ha sido desactivada. Contacta a la secretaría.',
                'errors'  => null,
            ], 403);
        }

        return $next($request);
    }
}
