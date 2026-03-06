<?php

use App\Http\Controllers\Admin\FxReconciliationController;
use App\Http\Controllers\Admin\TransactionChargeController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminServiceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DummyCscsController;
use App\Http\Controllers\Api\DummyNgxController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\Market\StockMarketController;
use App\Http\Controllers\Api\MarketController;
use App\Http\Controllers\Api\MarketDataController;
use App\Http\Controllers\Api\NewTransactionController;
use App\Http\Controllers\Api\OmsController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PaystackController;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SystemSettingsController;
use App\Http\Controllers\Api\TransactionTypeController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\User\LinkedAccountController;
use App\Http\Controllers\Api\User\NotificationController;
use App\Http\Controllers\Api\User\SecurityController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\DemoController;

// === ADVISORY CONTROLLERS ===
use App\Http\Controllers\AdvisoryController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\ModelPortfolioController;
use App\Http\Controllers\SubscriptionController;
// Admin Advisory Controllers 
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminAdvisoryController;
use App\Http\Controllers\Admin\AdminModelPortfolioController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
Route::post('/2fa/verify', [TwoFactorController::class, 'verify2FA']);
/* Paystack Webhook & Redirect */
Route::match(['get', 'post'], '/paystack/callback', [PaystackController::class, 'callback']);
Route::post('/paystack/webhook', [PaystackWebhookController::class, 'handle']);

Route::prefix('dummy')->group(function () {
    Route::prefix('ngx')->group(function () {
        Route::get('market/{symbol}', [DummyNgxController::class, 'marketData']);
        Route::post('orders', [DummyNgxController::class, 'placeOrder']);
        Route::get('orders/{order_id}', [DummyNgxController::class, 'orderStatus']);
        Route::get('trades/{id}', [DummyNgxController::class, 'tradeStatus']);
        Route::post('settle/{trade_id}', [DummyNgxController::class, 'settleTrade']);
        Route::get('quotes', [DummyNgxController::class, 'marketQuotes']);
        Route::get('trades', [DummyNgxController::class, 'tradeHistory']);
    });
    Route::prefix('cscs')->group(function () {
        Route::post('settle', [DummyCscsController::class, 'settle']);
        Route::get('settlement/{trade_id}', [DummyCscsController::class, 'settlementStatus']);
    });
});

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->get(
    '/market/candles',
    [MarketDataController::class, 'candles']
);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/markets/stocks/{symbol}/history', [StockMarketController::class, 'history']);
    Route::get('/markets/stocks/{symbol}/history', [\App\Http\Controllers\MarketDataController::class, 'stockHistory']);

    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);

    /* Wallet */
    Route::get('/wallet/balances', [WalletController::class, 'balances']);
    Route::post('/wallet/convert', [WalletController::class, 'convert'])->middleware('throttle:10,1');

    /* Paystack */
    Route::prefix('paystack')->group(function () {
        Route::post('/initiate', [PaystackController::class, 'initiate']);
        Route::get('/verify/{reference}', [PaystackController::class, 'verify']);
    });

    Route::get('/portfolio', [PortfolioController::class, 'index']);
    Route::get('/portfolio/history', [PortfolioController::class, 'performance']);

    // Route::get('/wallet/transactions', [WalletController::class, 'recentTransactions']);

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
    Route::get('/transactions/{id}', [NewTransactionController::class, 'show']);

    /* Reports */
    Route::post('/reports/generate', [ProfileController::class, 'generateReport']);

    /*Demo Mode Routes*/
    Route::post('/demo/start', [DemoController::class, 'startDemo']);   // fund demo wallet
    Route::post('/demo/reset', [DemoController::class, 'resetDemo']);   // reset demo account
    Route::post('/switch-mode', [ProfileController::class, 'switchMode']);


    /* Admin Routes */
    Route::middleware('admin')->prefix('admin')->group(function () {

        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // ==========================================
        // === ADMIN ADVISORY CONTROLS ===
        // ==========================================
        Route::apiResource('/subscription-plans', AdminSubscriptionController::class);
        Route::apiResource('/advisory-posts', AdminAdvisoryController::class);
        Route::apiResource('/model-portfolios', AdminModelPortfolioController::class);


        /* Users */
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{id}', [AdminController::class, 'userDetail']);
        Route::post('/users/{id}/toggle-status', [AdminController::class, 'toggleStatus']);
        Route::post('/users/{id}/role', [AdminController::class, 'updateUserRole']);

        /* Orders */
        Route::get('/orders', [AdminController::class, 'orders']);

        /* Transactions */
        Route::get('/transactions', [AdminController::class, 'transactions']);

        /* KYC */
        Route::get('/kycs', [AdminController::class, 'kycs']);
        Route::get('/kyc/{id}', [AdminController::class, 'getKyc']);
        Route::post('/kycs/{id}/review', [AdminController::class, 'reviewKyc']);
        Route::get('/kyc-settings', [AdminController::class, 'getKycSettings']);
        Route::post('/kyc-settings', [AdminController::class, 'updateKycSettings']);
        Route::delete('/admin/kyc-settings/{tier}', [AdminController::class, 'destroyKycSetting']);

        // FX rate management
        Route::post('/fx-rates', [\App\Http\Controllers\Admin\FxRateController::class, 'store']);

        /* Settings */
        Route::get('/settings', [SystemSettingsController::class, 'get']);
        Route::post('/settings', [SystemSettingsController::class, 'update']);

        /* Stats */
        Route::get('/stats', [AdminController::class, 'stats']);

        /* Control Panel */
        Route::get('/services', [AdminServiceController::class, 'index']);
        Route::post('/services', [AdminServiceController::class, 'store']);

        Route::put('/services/{id}', [AdminServiceController::class, 'update']);
        Route::patch('/services/{id}/toggle', [AdminServiceController::class, 'toggleService']);
        Route::get('/services/{id}/connections', [AdminServiceController::class, 'getConnections']);
        Route::post('/services/{id}/connections', [AdminServiceController::class, 'addConnection']);
        Route::put('/service-connections/{id}', [AdminServiceController::class, 'updateConnection']);
        Route::get('/services/{id}/config', [AdminServiceController::class, 'getConfig']);
        Route::put('/services/{id}/config', [AdminServiceController::class, 'updateConfig']);

        // Staff permissions management
        Route::get('/staff-permissions', [AdminController::class, 'getStaffPermissions']);
        Route::post('/staff-permissions', [AdminController::class, 'updateStaffPermissions']);

        Route::apiResource('transaction-types', TransactionTypeController::class);

        Route::get('/transaction-charges', [TransactionChargeController::class, 'index']);
        Route::put('/transaction-charges/{id}', [TransactionChargeController::class, 'update']);
        Route::post('/transaction-charges', [TransactionChargeController::class, 'store']);

        Route::get('/transactions', [AdminController::class, 'transactions']);
        Route::get('/earnings', [AdminController::class, 'getEarnings']);
        Route::get('/earnings/report', [AdminController::class, 'getEarningsReport']);
        Route::get('/transactions/export', [AdminController::class, 'exportTransactions']);

        // Activity Log
        Route::get('/activities', [AdminController::class, 'getActivityLogs']);
        Route::get('/activities/export', [AdminController::class, 'exportActivityLogs']);

        // Manual settlement processing
        Route::post('/settlements/process', function () {
            \Illuminate\Support\Facades\Artisan::call('settlements:process');
            return response()->json(['message' => 'Settlements processed successfully.']);
        });

        // FX Reconciliation
        Route::prefix('fx')->middleware('throttle:30,1')->group(function () {
            Route::get('/reconciliation', [FxReconciliationController::class, 'getReconciliation']);
            Route::get('/recent-transactions', [FxReconciliationController::class, 'getRecentTransactions']);
            Route::post('/run-reconciliation', [FxReconciliationController::class, 'runReconciliation']);
            Route::get('/pending-settlements', [FxReconciliationController::class, 'getPendingSettlements']);
        });

        // FX Admin Dashboard metrics
        Route::get('/fx-dashboard', [\App\Http\Controllers\Admin\FxDashboardController::class, 'index']);
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

        /*Notifications */
        Route::get('/notifications/show', [NotificationController::class, 'show']);
        Route::put('/notifications/update', [NotificationController::class, 'update']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        // === ADVISORY & SUBSCRIPTION MODULE ===
        // 
        Route::prefix('advisory')->group(function () {

            // Free Routes (Visible to anyone logged in)
            Route::get('/free-posts', [AdvisoryController::class, 'freePosts']);
            Route::get('/plans', [SubscriptionController::class, 'plans']);
            Route::post('/subscribe', [SubscriptionController::class, 'initializePayment']);
            Route::get('/verify-payment', [SubscriptionController::class, 'verifyPayment']);
            Route::post('/cancel', [SubscriptionController::class, 'cancelSubscription']);

            //VIP Routes (Locked behind the 'subscribed' middleware)
            Route::middleware('subscribed')->group(function () {
                Route::get('/premium-posts', [AdvisoryController::class, 'premiumPosts']);
                Route::get('/ai-picks', [PredictionController::class, 'topPicks']);
                Route::get('/model-portfolios', [ModelPortfolioController::class, 'index']);
                Route::post('/model-portfolios/{id}/copy', [ModelPortfolioController::class, 'copyPortfolio']);
            });
        });
    });
});
