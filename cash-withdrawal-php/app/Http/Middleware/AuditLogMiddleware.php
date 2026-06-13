<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    /**
     * Mutation methods that should be logged.
     */
    private const LOGGED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (in_array($request->method(), self::LOGGED_METHODS, true)) {
            $this->writeLog($request, $response);
        }

        return $response;
    }

    private function writeLog(Request $request, Response $response): void
    {
        try {
            AuditLog::create([
                'user_id'     => $request->get('__auth_user_id'),
                'action'      => $request->method() . ' ' . $request->path(),
                'entity_type' => $this->resolveEntityType($request->path()),
                'entity_id'   => $this->resolveEntityId($request),
                'old_value'   => null,
                'new_value'   => $request->except(['password', 'otp_code', 'sms_code']),
                'ip_address'  => $request->ip(),
            ]);
        } catch (\Throwable) {
            // Never let audit failure break the main flow
        }
    }

    private function resolveEntityType(string $path): string
    {
        if (str_contains($path, 'transaction')) {
            return 'transaction';
        }
        if (str_contains($path, 'dispenser') || str_contains($path, 'kiosk')) {
            return 'kiosk';
        }
        if (str_contains($path, 'agent')) {
            return 'agent';
        }
        if (str_contains($path, 'auth')) {
            return 'auth';
        }
        return 'unknown';
    }

    private function resolveEntityId(Request $request): ?int
    {
        $id = $request->route('id');
        return $id ? (int) $id : null;
    }
}
