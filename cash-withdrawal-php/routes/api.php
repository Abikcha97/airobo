<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DispenserController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kiosk Cash Withdrawal — API Routes
|--------------------------------------------------------------------------
| All routes are prefixed with /api/v1 (set in bootstrap)
| JWT auth applies to all routes except /auth/*
*/

// -----------------------------------------------------------------------
// Auth (public)
// -----------------------------------------------------------------------
Route::prefix('auth')->group(function () {
    Route::post('login',   [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('logout',  [AuthController::class, 'logout'])->middleware('jwt.auth');
});

// -----------------------------------------------------------------------
// Protected routes
// -----------------------------------------------------------------------
Route::middleware(['jwt.auth', 'audit.log'])->group(function () {

    // Transaction API — KIOSK role
    Route::middleware('role:KIOSK')->group(function () {
        Route::post('transactions/initiate',            [TransactionController::class, 'initiate']);
        Route::post('transactions/{id}/send-otp',       [TransactionController::class, 'sendOtp']);
        Route::post('transactions/{id}/verify-otp',     [TransactionController::class, 'verifyOtp']);
        Route::post('transactions/{id}/check',          [TransactionController::class, 'check']);
        Route::post('transactions/{id}/pay',            [TransactionController::class, 'pay']);
    });

    // Transaction status — all authenticated roles
    Route::get('transactions',                          [TransactionController::class, 'index']);
    Route::get('transactions/{id}/status',              [TransactionController::class, 'status']);

    // Refund — ADMIN only
    Route::middleware('role:ADMIN')->group(function () {
        Route::post('transactions/{id}/refund',         [TransactionController::class, 'refund']);
    });

    // Dispenser — AGENT or ADMIN
    Route::middleware('role:AGENT,ADMIN')->group(function () {
        Route::post('kiosks/{id}/dispenser/fill',       [DispenserController::class, 'fill']);
        Route::get('kiosks/{id}/dispenser/inventory',   [DispenserController::class, 'inventory']);
        Route::get('kiosks/{id}/dispenser/history',     [DispenserController::class, 'history']);
    });

    // Agent APIs — AGENT (own) or ADMIN
    Route::middleware('role:AGENT,ADMIN')->group(function () {
        Route::get('agents/{id}/deposit/balance',       [AgentController::class, 'depositBalance']);
        Route::get('agents/{id}/deposit/movements',     [AgentController::class, 'depositMovements']);
        Route::get('agents/{id}/rewards',               [AgentController::class, 'rewards']);
        Route::get('agents/{id}/reconciliation',        [AgentController::class, 'reconciliation']);
        Route::get('agents/{id}/dashboard',             [AgentController::class, 'dashboard']);
    });

    // Admin APIs — ADMIN only
    Route::middleware('role:ADMIN')->prefix('admin')->group(function () {
        Route::get('transactions',                       [AdminController::class, 'transactions']);
        Route::get('kiosks',                             [AdminController::class, 'kiosks']);
        Route::get('agents',                             [AdminController::class, 'agents']);
        Route::get('reports/summary',                    [AdminController::class, 'reportsSummary']);
    });
});
