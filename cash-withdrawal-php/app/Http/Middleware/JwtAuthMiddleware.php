<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (!$bearer) {
            return response()->json([
                'error'   => 'UNAUTHORIZED',
                'message' => 'Authorization header topilmadi',
            ], 401);
        }

        try {
            $token = $this->jwt->parseAccessToken($bearer);
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'INVALID_TOKEN',
                'message' => 'Token yaroqsiz yoki muddati tugagan',
            ], 401);
        }

        $claims = $token->claims();

        // Inject authenticated context
        $request->merge([
            '__auth_user_id'  => (int) $claims->get('sub'),
            '__auth_username' => $claims->get('username'),
            '__auth_role'     => $claims->get('role'),
            '__auth_agent_id' => $claims->get('agent_id'),
        ]);

        return $next($request);
    }
}
