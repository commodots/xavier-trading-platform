<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reporting Configuration
    |--------------------------------------------------------------------------
    |
    | This file defines the default configuration for the reporting framework.
    | It controls pagination, export settings, caching, and other reporting
    | behaviors across the application.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Pagination
    |--------------------------------------------------------------------------
    |
    | The default number of items to display per page in reports.
    |
    */
    'pagination' => 25,

    /*
    |--------------------------------------------------------------------------
    | Export Retention Days
    |--------------------------------------------------------------------------
    |
    | The number of days to retain exported report files before they are
    | automatically deleted by the DeleteExpiredExports job.
    |
    | Data Retention Policy:
    | - Audit Logs: Permanent
    | - Revenue Records: Permanent
    | - Portfolio Snapshots: Permanent
    | - Export Files: 90 Days (configurable)
    | - Analytics Snapshots: Permanent
    | - Cached Reports: 24 Hours
    |
    */
    'export_retention_days' => 90,

    /*
    |--------------------------------------------------------------------------
    | Cache Duration
    |--------------------------------------------------------------------------
    |
    | The default cache duration in seconds for expensive reports.
    | Default is 24 hours (86400 seconds).
    |
    | Cached Reports: 24 Hours
    |
    */
    'cache_duration' => 86400,

    /*
    |--------------------------------------------------------------------------
    | Max Export Rows
    |--------------------------------------------------------------------------
    |
    | The maximum number of rows allowed in a single export. Exports exceeding
    | this limit will be queued for background processing.
    |
    */
    'max_export_rows' => 10000,

    /*
    |--------------------------------------------------------------------------
    | Queue Connection
    |--------------------------------------------------------------------------
    |
    | The queue connection to use for report generation and export jobs.
    |
    */
    'queue_connection' =>  null,

    /*
    |--------------------------------------------------------------------------
    | Export Formats
    |--------------------------------------------------------------------------
    |
    | The supported export formats for reports.
    |
    */
    'export_formats' => [
        'excel' => 'Excel',
        'pdf' => 'PDF',
        'csv' => 'CSV',
        'print' => 'Print',
        'email' => 'Email',
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled Report Defaults
    |--------------------------------------------------------------------------
    |
    | Default settings for scheduled reports.
    |
    */
    'scheduled_reports' => [
        'enabled_by_default' => false,
        'default_frequency' => 'daily',
        'default_time' => '08:00',
        'email_subject' => 'Your Scheduled Report is Ready',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enabled Modules
    |--------------------------------------------------------------------------
    |
    | The reporting modules that are currently enabled. This allows for
    | granular control over which report families are available.
    |
    */
    'enabled_modules' => [
        'financial' => true,
        'investment' => true,
        'portfolio' => true,
        'revenue' => true,
        'compliance' => true,
        'operations' => true,
        'accounting' => true,
        'analytics' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for report caching behavior.
    |
    */
    'cache' => [
        'enabled' => true,
        'default_ttl' => 86400, // 24 hours
        'prefix' => 'reporting',
        'warm_cache_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Performance-related configuration for report generation.
    |
    */
    'performance' => [
        'enable_query_logging' => false,
        'slow_query_threshold' =>  1000, // ms
        'max_execution_time' =>  300, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for report audit logging.
    |
    */
    'audit' => [
        'enabled' => true,
        'log_exports' => true,
        'log_views' => true,
        'retention_days' => 365, // 1 year
    ],

    /*
    |--------------------------------------------------------------------------
    | Snapshot Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for report snapshots.
    |
    */
    'snapshots' => [
        'portfolio_enabled' => true,
        'analytics_enabled' => true,
        'revenue_enabled' => true,
        'retention_days' => 3650, // 10 years
    ],

];