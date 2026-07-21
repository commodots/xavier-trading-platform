<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        
        // Register report services
        $this->app->singleton(\App\Services\Reports\DashboardReportService::class);
        $this->app->singleton(\App\Services\Reports\UserReportService::class);
        $this->app->singleton(\App\Services\Reports\FinancialReportService::class);
        $this->app->singleton(\App\Services\Reports\InvestmentReportService::class);
        $this->app->singleton(\App\Services\Reports\WalletReportService::class);
        $this->app->singleton(\App\Services\Reports\WithdrawalReportService::class);
        $this->app->singleton(\App\Services\Reports\ReferralReportService::class);
        $this->app->singleton(\App\Services\Reports\SubscriptionReportService::class);
        $this->app->singleton(\App\Services\Reports\SystemReportService::class);
        $this->app->singleton(\App\Services\Reports\ReportExportService::class);
        $this->app->singleton(\App\Services\Reports\ReportCacheService::class);
        $this->app->bind(\App\Services\Reports\ReportQueryBuilder::class);
    }

    public function boot(): void
    {
        //
    }
}