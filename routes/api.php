<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Api\{
    OnboardingController,
    AuthController,
    PaystackController,
    ProfileController,
    KycController,
    OmsController,
    AdminController,
    MarketController,
    WalletController,
    SystemSettingsController,
    TwoFactorController,
    AdminServiceController,
    PortfolioController,
    ServiceConfigController,
    NewTransactionController,
    TransactionTypeController
};
use App\Http\Controllers\Auth\{
    PasswordResetLinkController,
    NewPasswordController
};
use App\Http\Controllers\Api\User\{
    LinkedAccountController,
    NotificationController,
    SecurityController
};
use App\Http\Controllers\Admin\{
    TransactionChargeController,
};
/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/onboard', [OnboardingController::class, 'onboard']);
Route::post('/bvn/verify', [OnboardingController::class, 'verifyBvn']);
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('api.password.email');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('api.password.store');
Route::post('/2fa/verify', [TwoFactorController::class, 'verify']);
/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);

    /* Wallet */
    Route::get('/wallet/balances', [WalletController::class, 'balances']);
    Route::post('/wallet/convert', [WalletController::class, 'convert']);

    Route::get('/portfolio', [PortfolioController::class, 'summary']);

    //Route::get('/wallet/transactions', [WalletController::class, 'recentTransactions']);

    // Authenticated Endpoints
    Route::get('/2fa/setup', [TwoFactorController::class, 'enable2FA']);
    Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm2FA']);
    Route::post('/2fa/disable', [TwoFactorController::class, 'disable2FA']);

    /* Profile */
    Route::get('/profile/me', [ProfileController::class, 'me']);
    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::get('/profile/kyc', [ProfileController::class, 'getKyc']);
    Route::post('/profile/kyc', [ProfileController::class, 'submitKyc']);


    /* Market */
    Route::get('/market/ngx', [MarketController::class, 'ngx']);
    Route::get('/market/global', [MarketController::class, 'global']);
    Route::get('/market/crypto', [MarketController::class, 'crypto']);

    /* Orders */
    Route::post('/orders', [OmsController::class, 'placeOrder']);
    Route::get('/orders', [OmsController::class, 'listOrders']);
    Route::post('/orders/{id}/cancel', [OmsController::class, 'cancelOrder']);

    Route::get('/transactions', [NewTransactionController::class, 'index']);
    Route::post('/deposit', [NewTransactionController::class, 'deposit']);
    Route::post('/withdraw', [NewTransactionController::class, 'withdraw']);

    /* Admin Routes */
    Route::middleware('admin')->prefix('admin')->group(function () {

        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        /* Users */
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{id}', [AdminController::class, 'userDetail']);
        Route::post('/users/{id}/toggle-status', [AdminController::class, 'toggleStatus']);
        Route::post('/users/{id}/role', [AdminController::class, 'updateUserRole']);

        /* Transactions */
        Route::get('/transactions', [AdminController::class, 'transactions']);

        /* KYC */
        Route::get('/kycs', [AdminController::class, 'kycs']);
        Route::post('/kycs/{id}/review', [AdminController::class, 'reviewKyc']);

        /* Settings */
        Route::get('/settings', [SystemSettingsController::class, 'get']);
        Route::post('/settings', [SystemSettingsController::class, 'update']);

        /* Stats */
        Route::get('/stats', [AdminController::class, 'stats']);

        /*Control Panel */
        Route::get('/services', [AdminServiceController::class, 'index']);
        Route::post('/services', [AdminServiceController::class, 'store']);
        Route::post('/services/{id}/connection', [AdminServiceController::class, 'addConnection']);
        Route::post('/services/{id}/activate', [AdminServiceController::class, 'toggleService']);

        Route::apiResource('transaction-types', TransactionTypeController::class);

        Route::get('/transaction-charges', [TransactionChargeController::class, 'index']);
        Route::put('/transaction-charges/{id}', [TransactionChargeController::class, 'update']);
        Route::post('/transaction-charges', [TransactionChargeController::class, 'store']);

        Route::get('/transactions', [AdminController::class, 'transactions']);
        Route::get('/earnings', [AdminController::class, 'getEarnings']);
        Route::get('/transactions/export', [AdminController::class, 'exportTransactions']);
    });
    Route::middleware(['auth:sanctum'])->prefix('user')->group(function () {
        Route::get('/kyc/show', [KycController::class, 'show']);
        Route::post('/kyc/submit', [KycController::class, 'submit']);

        Route::get('/profile/show', [ProfileController::class, 'show']);
        Route::put('/profile/update', [ProfileController::class, 'update']);

        Route::put('/security/password', [SecurityController::class, 'changePassword']);
        Route::post('/security/2fa/enable', [SecurityController::class, 'enable2FA']);
        Route::post('/security/2fa/verify', [SecurityController::class, 'verify2FA']);

        Route::get('/linked-accounts/index', [LinkedAccountController::class, 'index']);
        Route::post('/linked-accounts/store', [LinkedAccountController::class, 'store']);

        Route::get('/notifications/show', [NotificationController::class, 'show']);
        Route::put('/notifications/update', [NotificationController::class, 'update']);
    });
});
