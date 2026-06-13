<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * @param  string[]  $roles  Allowed roles for the route, e.g. ['ADMIN','AGENT']
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = $request->get('__auth_role');

        if (!$userRole || !in_array($userRole, $roles, true)) {
            return response()->json([
                'error'   => 'FORBIDDEN',
                'message' => 'Bu endpointga kirish uchun ruxsat yo\'q',
            ], 403);
        }

        return $next($request);
    }
}
