// config/logging.php

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', env('LOG_STACK', 'daily')),
            'ignore_exceptions' => false,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER', JsonFormatter::class),
            'formatter_with' => [
                'includeStacktraces' => true,
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        // ✅ This is the important part: define what Cloud injects
        'laravel-cloud-socket' => [
            // In most setups this is effectively "log to stderr as JSON"
            // while Cloud collects/ships it. If Cloud has its own custom driver
            // you can swap this later, but this works reliably for UI visibility.
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER', JsonFormatter::class),
            'formatter_with' => [
                'includeStacktraces' => true,
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],
    ],
];
