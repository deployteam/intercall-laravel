<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    |
    | Redis connection settings for inter-system communication.
    | The Redis transport uses these settings for pub/sub messaging.
    |
    */
    'redis' => [
        'connection' => env('INTERCALL_REDIS_CONNECTION', 'default'),
        'prefix' => env('INTERCALL_REDIS_PREFIX', 'intercall'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Token time-to-live for HMAC-signed request tokens.
    | This prevents replay attacks by expiring tokens after the specified time.
    |
    | Note: Authentication tokens are configured per-system using the
    | Intercall facade. See IntercallServiceProvider for examples.
    |
    */
    'auth' => [
        'token_ttl' => env('INTERCALL_TOKEN_TTL', 300), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Prevent abuse and overload with configurable rate limits.
    |
    */
    'rate_limit' => [
        'max_requests' => env('INTERCALL_RATE_LIMIT_MAX', 1000), // per minute
        'burst_limit' => env('INTERCALL_RATE_LIMIT_BURST', 50), // per 5 seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Serialization
    |--------------------------------------------------------------------------
    |
    | Serialization format for message encoding.
    | Options: msgpack (fastest), json (readable), gzip (compressed)
    |
    */
    'compression' => [
        'format' => env('INTERCALL_COMPRESSION_FORMAT', 'msgpack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Async Request Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for asynchronous request/response handling.
    |
    */
    'async' => [
        'status_ttl' => env('INTERCALL_ASYNC_STATUS_TTL', 3600), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | ACK Timeout
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) to wait for an acknowledgement from the remote
    | system before trying the next transport. If the remote system has
    | already acknowledged (persistent key), the dispatcher will wait
    | for the response instead of retrying.
    |
    */
    'ack_timeout' => env('INTERCALL_ACK_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | HTTP Fallback
    |--------------------------------------------------------------------------
    |
    | HTTP endpoint configuration for receiving requests when using
    | HTTP transport or as a fallback when Redis is unavailable.
    |
    */
    'http_fallback' => [
        'enabled' => env('INTERCALL_HTTP_FALLBACK_ENABLED', true),
        'endpoint' => env('INTERCALL_HTTP_ENDPOINT', '/api/intercall'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Logging configuration for inter-system communication.
    |
    | Available log levels:
    |   - none: Disable all logging
    |   - error: Only errors
    |   - warning: Errors and warnings
    |   - info: Errors, warnings, and info (default, recommended)
    |   - debug: All messages including verbose debug information
    |
    | Note: You can override the log level at runtime using command verbosity:
    |   php artisan intercall:listen     (error level)
    |   php artisan intercall:listen -v  (warning level)
    |   php artisan intercall:listen -vv (info level)
    |   php artisan intercall:listen -vvv (debug level)
    |
    */
    'logging' => [
        'channel' => env('INTERCALL_LOG_CHANNEL', 'stack'),
        'level' => env('INTERCALL_LOG_LEVEL', 'info'),
    ],
];
