<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        'horizon:'.Str::slug(env('APP_NAME', 'laravel'), '_')
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    */

    'waits' => [
        'redis:high' => 30,
        'redis:default' => 60,
        'redis:low' => 120,
        'redis:provisioning' => 180,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    */

    'trim' => [
        'recent' => 60,
        'pending' => 43200,       // 30 days
        'completed' => 60,
        'recent_failed' => 10080, // 1 week
        'failed' => 10080,        // 1 week
        'monitored' => 10080,     // 1 week
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    */

    'fast_termination' => true,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    */

    'memory_limit' => 128,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'supervisor-high' => [
            'connection' => 'redis',
            'queue' => ['high'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 2,
            'maxProcesses' => 10,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
            'timeout' => 60,
            'memory' => 512,
        ],
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'size',
            'minProcesses' => 1,
            'maxProcesses' => 5,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
            'timeout' => 120,
            'memory' => 512,
        ],
        'supervisor-provisioning' => [
            'connection' => 'redis',
            'queue' => ['provisioning'],
            'balance' => 'simple',
            'processes' => 3,
            'tries' => 3,
            'timeout' => 610, // MUST MATCH retry_after in queue.php
            'memory' => 512,
        ],
        'supervisor-low' => [
            'connection' => 'redis',
            'queue' => ['low'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'size',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
            'timeout' => 300,
            'memory' => 512,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-high' => [
                'maxProcesses' => 20,
                'balanceMaxShift' => 2,
            ],
            'supervisor-default' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 2,
            ],
            'supervisor-provisioning' => [
                'processes' => 5,
            ],
            'supervisor-low' => [
                'maxProcesses' => 5,
            ],
        ],

        'local' => [
            'supervisor-high' => [
                'maxProcesses' => 3,
            ],
            'supervisor-default' => [
                'maxProcesses' => 2,
            ],
            'supervisor-provisioning' => [
                'processes' => 1,
            ],
            'supervisor-low' => [
                'maxProcesses' => 2,
            ],
        ],
    ],
];
