<?php

namespace App\Providers;

use App\Services\Reports\DashboardReportService;
use App\Services\Reports\FinancialReportService;
use App\Services\Reports\InvestmentReportService;
use App\Services\Reports\ReferralReportService;
use App\Services\Reports\ReportCacheService;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportQueryBuilder;
use App\Services\Reports\SubscriptionReportService;
use App\Services\Reports\SystemReportService;
use App\Services\Reports\UserReportService;
use App\Services\Reports\WalletReportService;
use App\Services\Reports\WithdrawalReportService;
use Illuminate\Support\ServiceProvider;

class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {

        // Register report services
        $this->app->singleton(DashboardReportService::class);
        $this->app->singleton(UserReportService::class);
        $this->app->singleton(FinancialReportService::class);
        $this->app->singleton(InvestmentReportService::class);
        $this->app->singleton(WalletReportService::class);
        $this->app->singleton(WithdrawalReportService::class);
        $this->app->singleton(ReferralReportService::class);
        $this->app->singleton(SubscriptionReportService::class);
        $this->app->singleton(SystemReportService::class);
        $this->app->singleton(ReportExportService::class);
        $this->app->singleton(ReportCacheService::class);
        $this->app->bind(ReportQueryBuilder::class);
    }

    public function boot(): void
    {
        //
    }
}
