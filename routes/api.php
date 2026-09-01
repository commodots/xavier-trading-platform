<?php

use App\Http\Controllers\Admin\AdminAdvisoryController;
use App\Http\Controllers\Admin\AdminModelPortfolioController;
// Auth Controllers
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\BillingDashboardController;
use App\Http\Controllers\Admin\ComplianceController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\FxManagementController;
use App\Http\Controllers\Admin\FxRateController;
use App\Http\Controllers\Admin\FxReconciliationController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SettlementDashboardController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\AdvisoryController;
use App\Http\Controllers\AlpacaWebhookController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminServiceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CryptoController;
use App\Http\Controllers\Api\CryptoWebhookController;
use App\Http\Controllers\Api\DojahKycController;
use App\Http\Controllers\Api\DojahWebhookController;
use App\Http\Controllers\Api\DummyCscsController;
use App\Http\Controllers\Api\DummyNgxController;
use App\Http\Controllers\Api\FincraWebhookController;
use App\Http\Controllers\Api\FxConversionController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\MarketController;
use App\Http\Controllers\Api\MarketDataController;
use App\Http\Controllers\Api\NewTransactionController;
use App\Http\Controllers\Api\OmsController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PaystackController;
use App\Http\Controllers\Api\PaystackWebhookController;
use App\Http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\Security\AuditLogController;
use App\Http\Controllers\Api\Security\TwoFactorController;
// Admin Controllers
use App\Http\Controllers\Api\Security\UserDeviceController;
use App\Http\Controllers\Api\Security\WithdrawalController;
use App\Http\Controllers\Api\TradeController;
use App\Http\Controllers\Api\TransactionTypeController;
use App\Http\Controllers\Api\User\LinkedAccountController;
use App\Http\Controllers\Api\User\SecurityController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WatchlistController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DemoController;
// Dummy/Testing
use App\Http\Controllers\ModelPortfolioController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
//FixedIncome
use App\Http\Controllers\Api\Admin\FixedIncomeProductController;
use App\Http\Controllers\Api\FixedIncomeController;
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

Route::post('/login/verify-2fa', [TwoFactorController::class, 'verifyLogin'])->middleware('throttle:5,1');
Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])->middleware('throttle:5,1');
Route::post('/security/2fa/verify', [TwoFactorController::class, 'verifyLogin'])->middleware('throttle:5,1');

/* Webhooks (rate-limited to prevent abuse) */
Route::match(['get', 'post'], '/paystack/callback', [PaystackController::class, 'callback'])->name('paystack.callback')->middleware('throttle:30,1');
Route::post('/paystack/webhook', [PaystackWebhookController::class, 'handle'])->middleware('throttle:30,1');
Route::post('/crypto/webhook', [CryptoWebhookController::class, 'handle'])->middleware('throttle:30,1');
Route::post('/alpaca/webhook', [AlpacaWebhookController::class, 'handle'])->middleware('throttle:30,1');
Route::post('/fincra/webhook', [FincraWebhookController::class, 'handle'])->middleware('throttle:30,1');
Route::post('/webhooks/dojah', [DojahWebhookController::class, 'handle'])->middleware('throttle:30,1');
Route::post('/market/update', [TradeController::class, 'updateMarket'])->middleware('throttle:60,1');

Route::get('/stocks/search', [TradeController::class, 'searchSymbols']);
Route::post('/stocks/track', [TradeController::class, 'trackSymbol']);

Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['throttle:6,1'])
    ->name('api.verification.verify');

/* Mock Verification Sandboxes (Dummy API) */
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
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user/sessions', [SecurityController::class, 'getActiveSessions']);
    Route::post('/user/sessions/logout-others', [SecurityController::class, 'logoutOtherDevices']);
    Route::put('/user/security/password', [SecurityController::class, 'changePassword']);

    Route::post('/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['success' => false, 'message' => 'Email already verified.'], 400);
        }
        try {
            $request->user()->sendEmailVerificationNotification();

            return response()->json(['success' => true, 'message' => 'Verification link sent! Please check your email.']);
        } catch (Exception $e) {
            Log::error('Verification Email Error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json(['success' => false, 'message' => 'Failed to send link. Please retry verification.'], 500);
        }
    })->middleware('throttle:2,1');

    /* kyc Providers (Dojah) */
    Route::post('/kyc/verify-liveness', [DojahKycController::class, 'verifyLiveness']);
    Route::prefix('kyc')->group(function () {
        Route::post('/bvn', [DojahKycController::class, 'verifyBvn']);
        Route::post('/nin', [DojahKycController::class, 'verifyNin']);
        Route::post('/selfie', [DojahKycController::class, 'verifySelfie']);
        Route::get('/status', [DojahKycController::class, 'status']);
    });

    /* Basic Market Reads (Open to Unverified Accounts) */
    Route::middleware('kyc:0')->group(function () {
        Route::get('/market/candles', [MarketDataController::class, 'candles']);
        Route::get('/markets/stocks/{symbol}/history', [MarketDataController::class, 'stockHistory']);
        Route::get('/markets', [MarketController::class, 'index']);
        Route::get('/markets/insights/{market}', [TradeController::class, 'insights']);
        Route::get('/advisories', [AdvisoryController::class, 'index']);
        Route::get('/market/quotes', [MarketController::class, 'quotes']);
        Route::get('/market/ngx', [MarketController::class, 'ngx']);
        Route::get('/market/global', [MarketController::class, 'global']);
        Route::get('/market/crypto', [MarketController::class, 'crypto']);
        Route::get('/market/fixed-income', [MarketController::class, 'fixedIncome']);
        Route::get('/companies/search/{query}', [TradeController::class, 'searchSymbols']);
        Route::get('/market/ngx/insights', [MarketController::class, 'getNGXInsights']);
        Route::get('/market/global/insights', [MarketController::class, 'getGlobalInsights']);
    });

    /* Global Ledger & Structural Portfolios (Read Only) */
    Route::get('/wallet/balances', [WalletController::class, 'balances']);
    Route::get('/transactions', [NewTransactionController::class, 'index']);
    Route::get('/transactions/{id}', [NewTransactionController::class, 'show']);
    Route::get('/portfolio', [PortfolioController::class, 'index']);
    Route::get('/portfolio/history', [PortfolioController::class, 'performance']);
    Route::get('/portfolio/trading', [PortfolioController::class, 'trading']);
    Route::get('/fx-rates', [WalletController::class, 'getRates']);
    Route::get('/crypto/address', [CryptoController::class, 'getAddress']);

    // FX Conversion Routes
    Route::prefix('fx')->group(function () {
        Route::post('/quote', [FxConversionController::class, 'quote']);
        Route::post('/convert', [FxConversionController::class, 'convert']);
        Route::get('/history', [FxConversionController::class, 'history']);
    });

    Route::get('/orders', [OmsController::class, 'listOrders']);
    Route::get('/trade/positions', [TradeController::class, 'index']);
    Route::get('/trades', [TradeController::class, 'index']);

    /* Personal User Watchlist Configurations */
    Route::get('/watchlist', [WatchlistController::class, 'index']);
    Route::post('/watchlist', [WatchlistController::class, 'store']);
    Route::delete('/watchlist/{id}', [WatchlistController::class, 'destroy']);

    /* Reports */
    Route::prefix('reports')->group(function () {
        Route::get('/account-statement', [ReportController::class, 'accountStatement']);
        Route::get('/trading-performance', [ReportController::class, 'tradingPerformance']);
        Route::get('/history', [ReportController::class, 'reportHistory']);
    });

    /* Profile Modification & Sandboxes */
    Route::get('/profile/me', [ProfileController::class, 'show']);
    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::get('/profile/kyc', [ProfileController::class, 'getKyc']);
    Route::post('/profile/kyc', [ProfileController::class, 'submitKyc']);
    Route::post('/demo/start', [DemoController::class, 'startDemo']);
    Route::post('/demo/reset', [DemoController::class, 'resetDemo']);
    Route::post('/demo/trade', [DemoController::class, 'placeTrade']);
    Route::post('/switch-mode', [ProfileController::class, 'switchMode']);

    /* User Management Prefix */
    Route::prefix('user')->group(function () {
        Route::get('/kyc/show', [KycController::class, 'show']);
        Route::post('/kyc/submit', [KycController::class, 'submit']);
        Route::get('/profile/show', [ProfileController::class, 'show']);
        Route::post('/profile/update', [ProfileController::class, 'update']);

        Route::get('/linked-accounts/index', [LinkedAccountController::class, 'index']);
        Route::post('/linked-accounts/store', [LinkedAccountController::class, 'store']);
        Route::delete('/linked-accounts/{id}', [LinkedAccountController::class, 'destroy']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::get('/notifications/preferences', [NotificationController::class, 'showPreferences']);
        Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);

        /* Advisory Subscription Logic */
        Route::prefix('advisory')->group(function () {
            Route::get('/plans', [SubscriptionController::class, 'plans']);
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

    /* Security & Account Protection */
    Route::prefix('security')->group(function () {
        Route::prefix('2fa')->group(function () {
            Route::post('/setup', [TwoFactorController::class, 'setup']);
            Route::post('/confirm', [TwoFactorController::class, 'verify']);
            Route::post('/verify', [TwoFactorController::class, 'verifyLogin']);
            Route::post('/disable', [TwoFactorController::class, 'disable']);
            Route::get('/status', [TwoFactorController::class, 'status']);
        });

        // Enforcing KYC and 2FA via structural route-level middleware definitions
        Route::prefix('withdrawals')->middleware(['kyc:2', '2fa', 'throttle:3,60'])->group(function () {
            Route::get('/', [WithdrawalController::class, 'index']);
            Route::post('/', [WithdrawalController::class, 'store']);
            Route::post('/otp', [WithdrawalController::class, 'sendWithdrawalOtp']);
            Route::get('/{withdrawal}', [WithdrawalController::class, 'show']);
            Route::post('/{withdrawal}/approve', [WithdrawalController::class, 'approve']);
            Route::post('/{withdrawal}/reject', [WithdrawalController::class, 'reject']);
        });

        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [AuditLogController::class, 'index']);
            Route::get('/summary', [AuditLogController::class, 'summary']);
        });

        Route::prefix('devices')->group(function () {
            Route::get('/', [UserDeviceController::class, 'index']);
            Route::post('/register', [UserDeviceController::class, 'register']);
            Route::post('/{device}/trust', [UserDeviceController::class, 'trust']);
            Route::delete('/{device}', [UserDeviceController::class, 'revoke']);
        });
    });

    /* Verified Transaction Boundaries (Requires Verified Email) */
    Route::middleware('verified')->group(function () {
        Route::post('/wallet/convert', [WalletController::class, 'convert'])->middleware('throttle:10,1');
        Route::post('/transfer', [NewTransactionController::class, 'transfer']);
        Route::get('/account', [TradeController::class, 'account']);

        Route::post('/deposit', [NewTransactionController::class, 'deposit'])->middleware('kyc:1');
        Route::post('/crypto/withdraw', [CryptoController::class, 'withdraw'])->middleware(['kyc:2', '2fa', 'throttle:3,60']);

        /* Trading Operations */
        Route::middleware('kyc:1')->group(function () {
            Route::post('/orders', [OmsController::class, 'placeOrder'])->middleware('throttle:30,1');
            Route::post('/orders/{id}/cancel', [OmsController::class, 'cancelOrder']);
            Route::post('/trade/open', [TradeController::class, 'open']);
            Route::post('/trade/close/{id}', [TradeController::class, 'close'])->middleware('throttle:30,1');
            Route::post('/trade/place', [TradeController::class, 'placeOrder']);
        });
    });

    /* Financial Payment Integrations gateways */
    Route::prefix('paystack')->group(function () {
        Route::post('/initiate', [PaystackController::class, 'initiate']);
        Route::get('/verify/{reference}', [PaystackController::class, 'verify']);
    });

    Route::middleware('auth:sanctum')->prefix('fixed-income')->group(function () {

    Route::get('/products', [
        FixedIncomeController::class,
        'index'
    ]);

    Route::get('/products/{fixedIncomeProduct}', [
        FixedIncomeController::class,
        'show'
    ]);

    Route::post('/products/{fixedIncomeProduct}/calculate', [
        FixedIncomeController::class,
        'calculate'
    ]);

    Route::post('/products/{fixedIncomeProduct}/validate', [
        FixedIncomeController::class,
        'validateInvestment'
    ]);
});

    /* System Administrative Panel Layer */
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::apiResource('/subscription-plans', AdminSubscriptionController::class);
        Route::apiResource('/advisory-posts', AdminAdvisoryController::class);
        Route::apiResource('/model-portfolios', AdminModelPortfolioController::class);

        // Notifications
        Route::get('/notifications', [AdminNotificationController::class, 'index']);
        Route::get('/users/search', [AdminNotificationController::class, 'searchUsers']);
        Route::post('/notifications/send', [AdminNotificationController::class, 'send']);

        // User & Transaction Admin
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/orders', [AdminController::class, 'orders']);
        Route::get('/transactions', [AdminController::class, 'transactions']);
        Route::get('/activities', [AdminController::class, 'getActivityLogs']);
        Route::get('/audit-logs', [AdminController::class, 'getAuditLogs']);
        Route::get('/earnings', [AdminController::class, 'getEarnings']);

        // KYC Management
        Route::get('/kycs', [AdminController::class, 'kycs']);
        Route::get('/kyc/{id}', [AdminController::class, 'getKyc']);
        Route::post('/kycs/{id}/review', [AdminController::class, 'reviewKyc']);
        Route::get('/kyc-settings', [AdminController::class, 'getKycSettings']);
        Route::post('/kyc-settings', [AdminController::class, 'updateKycSettings']);

        // System Settings & FX
        Route::post('/fx-rates', [FxRateController::class, 'store']);
        Route::delete('/fx-rates/{id}', [FxRateController::class, 'destroy']);
        Route::get('/settings', [SystemSettingsController::class, 'get']);
        Route::post('/settings/update', [SystemSettingsController::class, 'updateSettings']);
        Route::get('/transaction-charges', [AdminController::class, 'getCharges']);
        Route::put('/transaction-charges/{id}', [AdminController::class, 'updateCharge']);
        Route::apiResource('transaction-types', TransactionTypeController::class);

        // Service Management
        Route::get('/services', [AdminServiceController::class, 'index']);
        Route::post('/services', [AdminServiceController::class, 'store']);
        Route::put('/services/{id}', [AdminServiceController::class, 'update']);
        Route::put('/services/{id}/mode', [AdminServiceController::class, 'updateMode']);
        Route::post('/services/{serviceId}/connection', [AdminServiceController::class, 'addConnection']);
        Route::patch('/services/{serviceId}/toggle', [AdminServiceController::class, 'toggleService']);
        Route::get('/services/{serviceId}/config', [AdminServiceController::class, 'getConfig']);
        Route::post('/services/{serviceId}/config', [AdminServiceController::class, 'updateConfig']);

        Route::prefix('fx')->group(function () {
            Route::get('/reconciliation', [FxReconciliationController::class, 'getReconciliation']);
            Route::post('/run-reconciliation', [FxReconciliationController::class, 'runReconciliation']);

            // FX Management (Provider switching, pairs, health)
            Route::get('/management', [FxManagementController::class, 'index']);
            Route::post('/switch-provider', [FxManagementController::class, 'switchProvider']);
            Route::put('/pairs/{id}', [FxManagementController::class, 'updatePair']);
            Route::get('/health', [FxManagementController::class, 'health']);
            Route::post('/toggle-auto-convert', [FxManagementController::class, 'toggleAutoConvert']);
            Route::get('/conversions', [FxManagementController::class, 'conversions']);
        });

        // ── Admin User Management ──
        Route::apiResource('users', AdminUserController::class);
        Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend']);
        Route::post('/users/{user}/unsuspend', [AdminUserController::class, 'unsuspend']);
        Route::post('/users/{user}/force-logout', [AdminUserController::class, 'forceLogout']);
        Route::post('/users/{user}/reset-2fa', [AdminUserController::class, 'reset2FA']);
        Route::post('/users/{user}/role', [AdminUserController::class, 'assignRole']);

        // ── Billing Dashboard ──
        Route::prefix('billing')->group(function () {
            Route::get('/summary', [BillingDashboardController::class, 'summary']);
            Route::get('/users', [BillingDashboardController::class, 'users']);
            Route::get('/renewals', [BillingDashboardController::class, 'renewals']);
            Route::get('/debts', [BillingDashboardController::class, 'debts']);
            Route::get('/revenue', [BillingDashboardController::class, 'revenue']);
        });

        // ── Settlement Dashboard ──
        Route::prefix('settlements')->group(function () {
            Route::get('/pending', [SettlementDashboardController::class, 'pending']);
            Route::get('/completed', [SettlementDashboardController::class, 'completed']);
            Route::get('/failed', [SettlementDashboardController::class, 'failed']);
            Route::get('/metrics', [SettlementDashboardController::class, 'metrics']);
            Route::post('/complete/{trade}', [SettlementDashboardController::class, 'complete']);
        });

        // ── Compliance Dashboard ──
        Route::prefix('compliance')->group(function () {
            Route::get('/kyc/pending', [ComplianceController::class, 'pending']);
            Route::get('/kyc/verified', [ComplianceController::class, 'verified']);
            Route::get('/kyc/rejected', [ComplianceController::class, 'rejected']);
            Route::get('/risk-flags', [ComplianceController::class, 'riskFlags']);
            Route::post('/risk-flags/{flag}/dismiss', [ComplianceController::class, 'dismissFlag']);
        });

        // ── Reports Module (Phase 1) ──
        Route::prefix('reports')->group(function () {
            Route::get('/dashboard', [ReportsController::class, 'dashboard']);
            Route::get('/users', [ReportsController::class, 'users']);
            Route::get('/users/summary', [ReportsController::class, 'userSummary']);
            Route::get('/users/filters', [ReportsController::class, 'userFilters']);
            Route::get('/users/{id}', [ReportsController::class, 'userDetail']);
            Route::get('/financial', [ReportsController::class, 'financial']);
            Route::get('/financial/summary', [ReportsController::class, 'financialSummary']);
            Route::get('/financial/summary/statistics', [ReportsController::class, 'financialStatistics']);
            Route::get('/investments', [ReportsController::class, 'investments']);
            Route::get('/investments/summary', [ReportsController::class, 'investmentSummary']);
            Route::get('/investments/charts', [ReportsController::class, 'investmentCharts']);
            Route::get('/investments/top-investors', [ReportsController::class, 'topInvestors']);
            Route::get('/investments/distribution', [ReportsController::class, 'investmentDistribution']);
            Route::get('/investments/filters', [ReportsController::class, 'investmentFilters']);
            Route::get('/wallet-withdrawals', [ReportsController::class, 'walletWithdrawals']);
            Route::get('/wallet-withdrawals/summary', [ReportsController::class, 'walletWithdrawalSummary']);
            Route::get('/referrals-subscriptions', [ReportsController::class, 'referralsSubscriptions']);
            Route::get('/referrals-subscriptions/summary', [ReportsController::class, 'referralSubscriptionSummary']);
            Route::get('/system', [ReportsController::class, 'system']);
            Route::get('/system/logs', [ReportsController::class, 'systemLogs']);
            Route::get('/system/integration-health', [ReportsController::class, 'integrationHealth']);
            Route::post('/clear-cache', [ReportsController::class, 'clearCache']);
            Route::post('/optimize', [ReportsController::class, 'optimize']);
            Route::post('/queue-restart', [ReportsController::class, 'queueRestart']);
            Route::post('/run-scheduler', [ReportsController::class, 'runScheduler']);
            Route::get('/logs', [ReportsController::class, 'viewLogs']);
            Route::get('/search-users', [ReportsController::class, 'searchUsers']);

            Route::get('/exec-dashboard', [ReportsController::class, 'execDashboard']);
            Route::get('/revenue', [ReportsController::class, 'revenue']);
            Route::get('/profit-loss', [ReportsController::class, 'profitLoss']);
            Route::get('/financial-summary', [ReportsController::class, 'financialSummaryReport']);
            Route::get('/executive-dashboard', [ReportsController::class, 'executiveDashboard']);
            Route::get('/expenses', [ReportsController::class, 'expenses']);
            Route::get('/downloads', [ReportsController::class, 'downloads']);
            Route::post('/export', [ReportsController::class, 'export']);

            // Module C: Investment & User Reports
            Route::get('/roi', [ReportsController::class, 'roi']);
            Route::get('/maturity', [ReportsController::class, 'maturity']);
            Route::get('/investment-plans', [ReportsController::class, 'investmentPlans']);
            Route::get('/subscriptions', [ReportsController::class, 'subscriptions']);
            Route::get('/kyc', [ReportsController::class, 'kyc']);
            Route::get('/login-history', [ReportsController::class, 'loginHistory']);
        });

        // ── Expense Management ──
        Route::prefix('expenses')->group(function () {
            Route::get('/', [ExpenseController::class, 'index']);
            Route::get('/create', [ExpenseController::class, 'create']);
            Route::post('/', [ExpenseController::class, 'store']);
            // Workflow actions must be declared before the implicit {expense} binding
            Route::post('/{expense}/approve', [ExpenseController::class, 'approve']);
            Route::post('/{expense}/pay', [ExpenseController::class, 'markPaid']);
            Route::post('/{expense}/cancel', [ExpenseController::class, 'cancel']);
            Route::get('/{expense}', [ExpenseController::class, 'show']);
            Route::get('/{expense}/edit', [ExpenseController::class, 'edit']);
            Route::put('/{expense}', [ExpenseController::class, 'update']);
            Route::delete('/{expense}', [ExpenseController::class, 'destroy']);
        });

        // ── Expense Categories, Vendors & Departments (API) ──
        Route::get('/expense-categories', [ExpenseController::class, 'categories']);
        Route::get('/vendors', [ExpenseController::class, 'vendors']);
        Route::get('/departments', [ExpenseController::class, 'departments']);

        // ── Expense Category & Vendor Management (API) ──
        // "/manage" endpoints list ALL records (including inactive) for the
        // administration pages; the plain index above only lists active ones
        // for form dropdowns.
        Route::prefix('expense-categories')->group(function () {
            Route::get('/manage', [ExpenseController::class, 'indexCategories']);
            Route::post('/store', [ExpenseController::class, 'storeCategory']);
            Route::put('/{category}', [ExpenseController::class, 'updateCategory']);
            Route::post('/{category}/toggle', [ExpenseController::class, 'toggleCategory']);
        });

        Route::prefix('vendors')->group(function () {
            Route::get('/manage', [ExpenseController::class, 'indexVendors']);
            Route::post('/store', [ExpenseController::class, 'storeVendor']);
            Route::put('/{vendor}', [ExpenseController::class, 'updateVendor']);
            Route::post('/{vendor}/toggle', [ExpenseController::class, 'toggleVendor']);
        });

        Route::get('/departments/manage', [DepartmentController::class, 'index']);
        Route::post('/departments/manage', [DepartmentController::class, 'store']);
        Route::put('/departments/manage/{department}', [DepartmentController::class, 'update']);
        Route::delete('/departments/manage/{department}', [DepartmentController::class, 'destroy']);
        Route::post('/departments/manage/{department}/toggle', [DepartmentController::class, 'toggle']);
    });

    // ── Fixed Income Product Management ──
    Route::prefix('fixed-income')->group(function () {

        Route::get('/products', [
            FixedIncomeProductController::class,
            'index'
        ]);

        Route::post('/products', [
            FixedIncomeProductController::class,
            'store'
        ]);

        Route::get('/products/{fixedIncomeProduct}', [
            FixedIncomeProductController::class,
            'show'
        ]);

        Route::put('/products/{fixedIncomeProduct}', [
            FixedIncomeProductController::class,
            'update'
        ]);

        Route::delete('/products/{fixedIncomeProduct}', [
            FixedIncomeProductController::class,
            'destroy'
        ]);

        Route::post('/products/{fixedIncomeProduct}/activate', [
            FixedIncomeProductController::class,
            'activate'
        ]);

        Route::post('/products/{fixedIncomeProduct}/suspend', [
            FixedIncomeProductController::class,
            'suspend'
        ]);

        Route::post('/products/{fixedIncomeProduct}/close', [
            FixedIncomeProductController::class,
            'close'
        ]);
    });
});
