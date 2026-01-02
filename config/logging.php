<?php

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [
    // ...

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'daily,stderr')),
            'ignore_exceptions' => false,
        ],

        // ...

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],

            // ✅ default to JSON so Cloud UIs can parse it
            'formatter' => env('LOG_STDERR_FORMATTER', JsonFormatter::class),
            'formatter_with' => [
                // ✅ super helpful in cloud logs
                'includeStacktraces' => true,
            ],

            'processors' => [PsrLogMessageProcessor::class],
        ],

        // ...
    ],
];
