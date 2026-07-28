<?php

return [

    'backup' => [
        'name' => env('APP_NAME', 'Xavier Trading Platform'),
        'source' => [
            'files' => [
                'include' => [
                    base_path('app'),
                    base_path('bootstrap'),
                    base_path('config'),
                    base_path('database'),
                    base_path('public'),
                    base_path('resources'),
                    base_path('routes'),
                    base_path('storage'),
                    base_path('tests'),
                    base_path('composer.json'),
                    base_path('composer.lock'),
                    base_path('package.json'),
                    base_path('.env'),
                ],
                'exclude' => [
                    base_path('storage/app/public'),
                    base_path('storage/framework/cache'),
                    base_path('storage/framework/sessions'),
                    base_path('storage/framework/views'),
                    base_path('storage/logs'),
                ],
                'relative_path' => base_path(),
            ],

            'databases' => [
                'mysql',
            ],
        ],

        'destination' => [
            'filename' => env('APP_NAME') . '_' . now()->format('Y-m-d_H-i-s') . '.zip',
            'disk' => 'local',
        ],

        'notifications' => [
            'notifications' => [
                \Spatie\Backup\Notifications\Notifications\BackupHasFailed::class => ['mail'],
                \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFound::class => ['mail'],
                \Spatie\Backup\Notifications\Notifications\CleanupHasFailed::class => ['mail'],
                \Spatie\Backup\Notifications\Notifications\BackupWasSuccessful::class => ['mail'],
                \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFound::class => ['mail'],
                \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessful::class => ['mail'],
            ],
        ],
    ],

];