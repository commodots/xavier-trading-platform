<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Reporting\Contracts\ReportInterface;
use App\Reporting\Contracts\ExportInterface;
use App\Reporting\Contracts\CacheInterface;
use App\Reporting\Contracts\FilterInterface;
use App\Reporting\Registry\ReportRegistry;
use App\Reporting\Factories\ReportFactory;
use App\Reporting\Factories\ExportFactory;
use App\Reporting\Factories\WidgetFactory;
use App\Reporting\Cache\ReportCache;
use App\Reporting\Services\AccountStatementService;
use App\Reporting\Services\DepositReportService;
use App\Reporting\Services\WithdrawalReportService;
use App\Reporting\Services\AuditTrailService;
use App\Reporting\Services\AuditService;
use App\Reporting\Builders\TradeHistoryBuilder;
use App\Reporting\Builders\PortfolioBuilder;
use App\Reporting\Builders\RevenueBuilder;
use App\Reporting\Builders\StatementBuilder;
use App\Reporting\Repositories\TransactionRepository;
use App\Reporting\Repositories\TradeRepository;
use App\Reporting\Repositories\PortfolioRepository;
use App\Reporting\Repositories\RevenueRepository;
use App\Reporting\Repositories\AuditRepository;
use App\Reporting\Support\Paginator;
use App\Reporting\Support\Sorter;
use App\Reporting\Support\Aggregator;
use App\Reporting\Support\Calculator;
use App\Reporting\Support\Formatter;

class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/reporting.php'), 'reporting'
        );

        $this->registerContracts();
        $this->registerRepositories();
        $this->registerServices();
        $this->registerBuilders();
        $this->registerFilters();
        $this->registerFactories();
        $this->registerSupportClasses();
        $this->registerRegistry();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                base_path('config/reporting.php') => config_path('reporting.php'),
            ], 'reporting-config');
        }

        $this->registerReportEvents();
    }

    protected function registerContracts(): void
    {
        // ReportInterface: Every report implements this with generate(ReportFilterData): array
        $this->app->bind(ReportInterface::class, function ($app) {
            return $app->make(ReportRegistry::class);
        });

        $this->app->bind(ExportInterface::class, function ($app) {
            return $app->make(ExportFactory::class);
        });

        $this->app->singleton(CacheInterface::class, function ($app) {
            return new ReportCache(
                cache_duration: config('reporting.cache.default_ttl', config('reporting.cache_duration', 86400)),
                prefix: config('reporting.cache.prefix', 'reporting'),
                enabled: config('reporting.cache.enabled', true)
            );
        });

        $this->app->bind(FilterInterface::class, function ($app) {
            return $app->make(\App\Reporting\Filters\FilterRegistry::class);
        });
    }

    protected function registerRepositories(): void
    {
        $this->app->singleton(TransactionRepository::class, fn() => new TransactionRepository());
        $this->app->singleton(TradeRepository::class, fn() => new TradeRepository());
        $this->app->singleton(PortfolioRepository::class, fn() => new PortfolioRepository());
        $this->app->singleton(RevenueRepository::class, fn() => new RevenueRepository());
        $this->app->singleton(AuditRepository::class, fn() => new AuditRepository());
    }

    protected function registerServices(): void
    {
        $enabledModules = config('reporting.enabled_modules', []);

        if ($enabledModules['operations'] ?? true) {
            $this->app->singleton(AccountStatementService::class);
        }
        if ($enabledModules['financial'] ?? true) {
            $this->app->singleton(DepositReportService::class);
            $this->app->singleton(WithdrawalReportService::class);
        }
        $this->app->singleton(AuditTrailService::class);
        $this->app->singleton(AuditService::class);
    }

    protected function registerBuilders(): void
    {
        $this->app->singleton(TradeHistoryBuilder::class);
        $this->app->singleton(PortfolioBuilder::class);
        $this->app->singleton(RevenueBuilder::class);
        $this->app->singleton(StatementBuilder::class);
    }

    protected function registerFactories(): void
    {
        $this->app->singleton(ReportFactory::class, function ($app) {
            return new ReportFactory(
                $app->make(ReportRegistry::class),
                $app->make(Paginator::class),
                $app->make(Formatter::class)
            );
        });

        $this->app->singleton(ExportFactory::class, function ($app) {
            return new ExportFactory(
                maxRows: config('reporting.max_export_rows', 10000),
                queueConnection: config('reporting.queue_connection')
            );
        });

        $this->app->singleton(WidgetFactory::class);
    }

    protected function registerFilters(): void
    {
        $this->app->singleton(\App\Reporting\Filters\FilterRegistry::class, function ($app) {
            $registry = new \App\Reporting\Filters\FilterRegistry();
            
            $registry->register('date', new \App\Reporting\Filters\DateFilter());
            $registry->register('currency', new \App\Reporting\Filters\CurrencyFilter());
            $registry->register('status', new \App\Reporting\Filters\StatusFilter());
            $registry->register('user', new \App\Reporting\Filters\UserFilter());
            
            return $registry;
        });
    }
    protected function registerSupportClasses(): void
    {
        $this->app->singleton(Paginator::class, function () {
            return new Paginator(defaultPerPage: config('reporting.pagination', 25));
        });
        $this->app->singleton(Sorter::class, fn() => new Sorter());
        $this->app->singleton(Aggregator::class, fn() => new Aggregator());
        $this->app->singleton(Calculator::class, fn() => new Calculator());
        $this->app->singleton(Formatter::class, fn() => new Formatter());
    }
    protected function registerRegistry(): void
    {
        $this->app->singleton(ReportRegistry::class, function () {
            $registry = new ReportRegistry();
            $enabledModules = config('reporting.enabled_modules', []);
            $reportConfigs = config('reporting.reports', []);

            foreach ($reportConfigs as $category => $reports) {
                if ($enabledModules[$category] ?? true) {
                    foreach ($reports as $name => $reportClass) {
                        $registry->register($category, $name, $reportClass);
                    }
                }
            }
            return $registry;
        });
    }
    protected function registerReportEvents(): void
    {
        Event::listen(
            \App\Reporting\Events\ReportGenerated::class,
            \App\Reporting\Listeners\HandleReportGenerated::class
        );
        Event::listen(
            \App\Reporting\Events\ReportExportCompleted::class,
            \App\Reporting\Listeners\HandleExportCompleted::class
        );
    }
}