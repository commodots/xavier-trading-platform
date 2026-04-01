<?php

use App\Http\Controllers\Admin\AdminAdvisoryController;
use App\Http\Controllers\Admin\AdminModelPortfolioController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\FxReconciliationController;
use App\Http\Controllers\Admin\FxDashboardController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\TransactionChargeController;
use App\Http\Controllers\AdvisoryController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminServiceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CryptoController;
use App\Http\Controllers\Api\CryptoWebhookController;
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
use App\Http\Controllers\Api\PaystackWebhookController;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TradeController;
use App\Http\Controllers\Api\TransactionTypeController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\User\LinkedAccountController;
use App\Http\Controllers\Api\User\NotificationController;
use App\Http\Controllers\Api\User\SecurityController;

// === ADVISORY CONTROLLERS ===
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DemoController;
// Admin Advisory Controllers
use App\Http\Controllers\ModelPortfolioController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckSubscription;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/onboard', [OnboardingController::class, 'onboard']);
Route::post('/bvn/verify', [OnboardingController::class, 'verifyBvn']);
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('api.password.email');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('api.password.store');
Route::post('/2fa/verify', [TwoFactorController::class, 'verify2FA'])->middleware('throttle:5,1');
/* Paystack Webhook & Redirect */
Route::match(['get', 'post'], '/paystack/callback', [PaystackController::class, 'callback']);
Route::post('/paystack/webhook', [PaystackWebhookController::class, 'handle']);
Route::post('/crypto/webhook', [CryptoWebhookController::class, 'handle']);

/* Email Verification (API link for SPA, does not require prior auth, uses signed link) */
Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('api.verification.verify');

/* Dummy API for Testing */
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
| Protected Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->get(
    '/market/candles',
    [MarketDataController::class, 'candles']
);

Route::middleware('auth:sanctum')->group(function () {

    /* User & Auth */
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);

    /* Resend Email Verification */
    Route::post('/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Already verified.'], 400);
        }
        $request->user()->sendEmailVerificationNotification();

        return response()->json(['status' => 'verification-link-sent']);
    })->middleware(['throttle:6,1'])->name('verification.send');

    /* Market Data */
    Route::get('/market/candles', [MarketDataController::class, 'candles']);
    Route::get('/markets/stocks/{symbol}/history', [MarketDataController::class, 'stockHistory']);
    Route::get('/market/ngx', [MarketController::class, 'ngx']);
    Route::get('/market/global', [MarketController::class, 'global']);
    Route::get('/market/crypto', [MarketController::class, 'crypto']);
    Route::get('/market/fixed-income', [MarketController::class, 'fixedIncome']);

    /* Wallet & Transactions (Verified Only) */
    Route::middleware('verified')->group(function () {
        Route::get('/wallet/balances', [WalletController::class, 'balances']);
        Route::post('/wallet/convert', [WalletController::class, 'convert'])->middleware('throttle:10,1');
        Route::get('/transactions', [NewTransactionController::class, 'index']);
        Route::post('/deposit', [NewTransactionController::class, 'deposit']);
        Route::post('/withdraw', [NewTransactionController::class, 'withdraw']);
        Route::post('/transfer', [NewTransactionController::class, 'transfer']);
        Route::get('/fx-rates', [WalletController::class, 'getRates']);
        Route::get('/transactions/{id}', [NewTransactionController::class, 'show']);

        /* Crypto */
        Route::get('/crypto/address', [CryptoController::class, 'getAddress']);
        Route::post('/crypto/withdraw', [CryptoController::class, 'withdraw']);
    });

    /* Paystack Integration */
    Route::prefix('paystack')->group(function () {
        Route::post('/initiate', [PaystackController::class, 'initiate']);
        Route::get('/verify/{reference}', [PaystackController::class, 'verify']);
    });

    /* Portfolio & Trading (Verified Only) */
    Route::middleware('verified')->group(function () {
        Route::get('/portfolio', [PortfolioController::class, 'index']);
        Route::get('/portfolio/history', [PortfolioController::class, 'performance']);
        Route::post('/orders', [OmsController::class, 'placeOrder']);
        Route::get('/orders', [OmsController::class, 'listOrders']);
        Route::post('/orders/{id}/cancel', [OmsController::class, 'cancelOrder']);
        Route::post('/trade/open', [TradeController::class, 'open']);
        Route::post('/trade/close/{id}', [TradeController::class, 'close']);
        Route::get('/trades', [TradeController::class, 'index']);
    });

    /* Profile & Security */
    Route::get('/profile/me', [ProfileController::class, 'me']);
    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::get('/profile/kyc', [ProfileController::class, 'getKyc']);
    Route::post('/profile/kyc', [ProfileController::class, 'submitKyc']);
    Route::get('/2fa/setup', [TwoFactorController::class, 'enable2FA']);
    Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm2FA']);
    Route::post('/2fa/disable', [TwoFactorController::class, 'disable2FA']);
    Route::post('/reports/generate', [ProfileController::class, 'generateReport']);

    /* Demo Mode */
    Route::post('/demo/start', [DemoController::class, 'startDemo']);
    Route::post('/demo/reset', [DemoController::class, 'resetDemo']);
    Route::post('/demo/trade', [DemoController::class, 'placeTrade']);
    Route::post('/switch-mode', [ProfileController::class, 'switchMode']);

    /* User Sub-Prefix */
    Route::prefix('user')->group(function () {
        Route::get('/kyc/show', [KycController::class, 'show']);
        Route::post('/kyc/submit', [KycController::class, 'submit']);
        Route::get('/profile/show', [ProfileController::class, 'show']);
        Route::put('/profile/update', [ProfileController::class, 'update']);
        Route::put('/security/password', [SecurityController::class, 'changePassword']);
        Route::post('/security/2fa/enable', [SecurityController::class, 'enable2FA']);
        Route::post('/security/2fa/verify', [SecurityController::class, 'verify2FA']);
        Route::get('/linked-accounts/index', [LinkedAccountController::class, 'index']);
        Route::post('/linked-accounts/store', [LinkedAccountController::class, 'store']);
        Route::delete('/linked-accounts/{id}', [LinkedAccountController::class, 'destroy']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::get('/notifications/preferences', [NotificationController::class, 'showPreferences']);
        Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);

        /* Advisory within User */
        Route::prefix('advisory')->group(function () {
            Route::post('/activate-trial', [AdvisoryController::class, 'activateTrial']);
            Route::post('/subscribe', [SubscriptionController::class, 'initializePayment']);
            Route::get('/verify-payment', [SubscriptionController::class, 'verifyPayment']);
            Route::post('/cancel', [SubscriptionController::class, 'cancelSubscription']);

            Route::middleware('advisory.access:regular')->group(function () {
                Route::get('/regular-posts', [AdvisoryController::class, 'regularPosts']);
            });

            Route::middleware('advisory.access:premium')->group(function () {
                Route::get('/premium-posts', [AdvisoryController::class, 'premiumPosts']);
                Route::get('/ai-picks', [PredictionController::class, 'topPicks']);
                Route::get('/model-portfolios', [ModelPortfolioController::class, 'index']);
                Route::post('/model-portfolios/{id}/copy', [ModelPortfolioController::class, 'copyPortfolio']);
            });
        });
    });

    /* Admin Routes */
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::apiResource('/subscription-plans', AdminSubscriptionController::class);
        Route::apiResource('/advisory-posts', AdminAdvisoryController::class);
        Route::apiResource('/model-portfolios', AdminModelPortfolioController::class);
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/orders', [AdminController::class, 'orders']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{id}', [AdminController::class, 'userDetail']);
        Route::post('/users/{id}/toggle-status', [AdminController::class, 'toggleStatus']);
        Route::post('/users/{id}/role', [AdminController::class, 'updateUserRole']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/transactions', [AdminController::class, 'transactions']);
        Route::get('/transactions/export', [AdminController::class, 'exportTransactions']);
        Route::get('/activities', [AdminController::class, 'getActivityLogs']);
        Route::get('/activities/export', [AdminController::class, 'exportActivityLogs']);
        Route::get('/earnings', [AdminController::class, 'getEarnings']);
        Route::get('/earnings/report', [AdminController::class, 'getEarningsReport']);
        Route::get('/permissions', [AdminController::class, 'getStaffPermissions']);
        Route::post('/permissions', [AdminController::class, 'updateStaffPermissions']);
        Route::get('/fx-dashboard', [FxDashboardController::class, 'index']);
        Route::get('/kycs', [AdminController::class, 'kycs']);
        Route::get('/kyc/{id}', [AdminController::class, 'getKyc']);
        Route::post('/kycs/{id}/review', [AdminController::class, 'reviewKyc']);

        // KYC Settings & Tier management
        Route::get('/kyc-settings', [AdminController::class, 'getKycSettings']);
        Route::post('/kyc-settings', [AdminController::class, 'updateKycSettings']);
        Route::delete('/kyc-settings/{tier}', [AdminController::class, 'destroyKycSetting']);

        Route::post('/fx-rates', [\App\Http\Controllers\Admin\FxRateController::class, 'store']);
        Route::get('/settings', [SystemSettingsController::class, 'get']);
        Route::post('/settings/update', [SystemSettingsController::class, 'update']);
        Route::get('/transaction-charges', [AdminController::class, 'getCharges']);
        Route::put('/transaction-charges/{id}', [AdminController::class, 'updateCharge']);
        Route::apiResource('transaction-types', TransactionTypeController::class);

        // Service management
        Route::get('/services', [\App\Http\Controllers\Api\AdminServiceController::class, 'index']);
        Route::post('/services', [\App\Http\Controllers\Api\AdminServiceController::class, 'store']);
        Route::put('/services/{id}', [\App\Http\Controllers\Api\AdminServiceController::class, 'update']);
        Route::put('/services/{id}/mode', [\App\Http\Controllers\Api\AdminServiceController::class, 'updateMode']);
        Route::post('/services/{serviceId}/connection', [\App\Http\Controllers\Api\AdminServiceController::class, 'addConnection']);
        Route::patch('/services/{serviceId}/toggle', [\App\Http\Controllers\Api\AdminServiceController::class, 'toggleService']);
        Route::get('/services/{serviceId}/connections', [\App\Http\Controllers\Api\AdminServiceController::class, 'getConnections']);
        Route::put('/services/connections/{connectionId}', [\App\Http\Controllers\Api\AdminServiceController::class, 'updateConnection']);
        Route::get('/services/{serviceId}/config', [\App\Http\Controllers\Api\AdminServiceController::class, 'getConfig']);
        Route::post('/services/{serviceId}/config', [\App\Http\Controllers\Api\AdminServiceController::class, 'updateConfig']);

        // FX Reconciliation
        Route::prefix('fx')->group(function () {
            Route::get('/dashboard', [FxDashboardController::class, 'index']);
            Route::get('/reconciliation', [FxReconciliationController::class, 'getReconciliation']);
            Route::post('/run-reconciliation', [FxReconciliationController::class, 'runReconciliation']);
            Route::get('/transactions', [FxReconciliationController::class, 'getRecentTransactions']);
            Route::get('/pending-settlements', [FxReconciliationController::class, 'getPendingSettlements']);
        });
    });

});
