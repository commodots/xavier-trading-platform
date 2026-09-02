<?php

use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\PlatformSettingsController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\SettlementDashboardController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', function () {
    return redirect('/dashboard');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'database' => \DB::connection()->getPdo() !== false,
        'redis' => \Cache::store('redis')->get('health_check') !== null,
        'queue' => true,
    ]);
});

// Protected routes
Route::middleware('auth')->group(function () {
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->name('password.confirm.store');
    Route::put('password', [PasswordController::class, 'update'])
        ->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Dashboard
    Route::get('/dashboard', function () {
        return view('app');
    })->name('dashboard');

    // Wallet
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/deposit', [WalletController::class, 'deposit'])->name('wallet.deposit');
    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw'])->name('wallet.withdraw');
});

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [SettlementDashboardController::class, 'index'])->name('dashboard');
    Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/send', [AdminNotificationController::class, 'send'])->name('notifications.send');
    Route::get('/settings', [PlatformSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [PlatformSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/clear-cache', [PlatformSettingsController::class, 'clearCache'])->name('settings.clear-cache');

    // Departments
    Route::get('/departments', function () {
        return view('app');
    })->name('departments.index');

    // Audit logs
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    Route::get('/audit/export', [AuditController::class, 'export'])->name('audit.export');

    // Security
    Route::get('/security/login-history', [SecurityController::class, 'loginHistory'])->name('security.login-history');
    Route::get('/security/active-sessions', [SecurityController::class, 'activeSessions'])->name('security.active-sessions');
    Route::delete('/security/sessions/{id}', [SecurityController::class, 'logoutSession'])->name('security.logout-session');
    Route::delete('/security/sessions/user/{userId}', [SecurityController::class, 'logoutAllSessions'])->name('security.logout-all-sessions');
});

// Catch-all route for Vue Router
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
