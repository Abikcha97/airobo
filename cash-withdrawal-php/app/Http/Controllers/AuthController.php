<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

class AuthController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCK_MINUTES       = 30;

    public function __construct(
        private readonly JwtService $jwt,
    ) {}

    /**
     * POST /api/v1/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string',
        ]);

        /** @var User|null $user */
        $user = User::where('username', $request->username)->first();

        if (!$user || !$user->is_active) {
            return response()->json([
                'error'   => 'INVALID_CREDENTIALS',
                'message' => 'Login yoki parol noto\'g\'ri',
            ], 401);
        }

        // Check account lock
        if ($user->locked_until && now()->lt($user->locked_until)) {
            return response()->json([
                'error'       => 'ACCOUNT_LOCKED',
                'message'     => 'Hisob vaqtincha bloklangan',
                'retry_after' => now()->diffInSeconds($user->locked_until),
            ], 423);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password_hash)) {
            $user->increment('failed_login_attempts');

            if ($user->failed_login_attempts >= self::MAX_LOGIN_ATTEMPTS) {
                $user->update(['locked_until' => now()->addMinutes(self::LOCK_MINUTES)]);
            }

            return response()->json([
                'error'   => 'INVALID_CREDENTIALS',
                'message' => 'Login yoki parol noto\'g\'ri',
            ], 401);
        }

        // Reset on success
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ]);

        $accessToken  = $this->jwt->issueAccessToken([
            'sub'      => $user->id,
            'username' => $user->username,
            'role'     => $user->role,
            'agent_id' => $user->agent_id,
        ]);

        $refreshToken = $this->jwt->issueRefreshToken();
        $ttl          = config('jwt.refresh_ttl', 2592000);

        // Store refresh token in Redis
        Redis::setex("refresh:{$refreshToken}", $ttl, $user->id);

        return response()->json([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => config('jwt.access_ttl', 3600),
            'role'          => $user->role,
        ]);
    }

    /**
     * POST /api/v1/auth/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['refresh_token' => 'required|string']);

        $userId = Redis::get("refresh:{$request->refresh_token}");

        if (!$userId) {
            return response()->json([
                'error'   => 'INVALID_REFRESH_TOKEN',
                'message' => 'Refresh token yaroqsiz yoki muddati tugagan',
            ], 401);
        }

        $user = User::findOrFail($userId);

        $accessToken = $this->jwt->issueAccessToken([
            'sub'      => $user->id,
            'username' => $user->username,
            'role'     => $user->role,
            'agent_id' => $user->agent_id,
        ]);

        return response()->json([
            'access_token' => $accessToken,
            'expires_in'   => config('jwt.access_ttl', 3600),
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token');

        if ($refreshToken) {
            Redis::del("refresh:{$refreshToken}");
        }

        return response()->json(['message' => 'Tizimdan chiqildi']);
    }
}
