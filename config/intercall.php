<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Token time-to-live for HMAC-signed request tokens.
    | This prevents replay attacks by expiring tokens after the specified time.
    |
    | Per-system tokens: Each remote system can have one or more pre-shared
    | secrets configured in the 'current-system' -> 'tokens' array below.
    | Multiple tokens per system enable graceful token rotation.
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
    | system before trying the next transport.
    |
    */
    'ack_timeout' => env('INTERCALL_ACK_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Shutdown Timeout
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) to wait for workers to finish their current
    | request before force-killing them during reload or shutdown.
    |
    */
    'shutdown_timeout' => env('INTERCALL_SHUTDOWN_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Listener Heartbeat
    |--------------------------------------------------------------------------
    |
    | Heartbeat checks verify that remote systems have active listeners
    | before sending async callbacks. This prevents messages from accumulating
    | in queues when no listener is running.
    |
    | How it works:
    | - Each system exposes an HTTP endpoint: /intercall/heartbeat
    | - Before sending async callbacks, systems check this endpoint
    | - If heartbeat fails, a warning is logged but message is still sent
    |
    | Storage path: Directory where heartbeat files are stored (for systems without Redis).
    | Defaults to storage_path('intercall') in Laravel, or sys_get_temp_dir() in standalone.
    |
    */
    'heartbeat' => [
        'enabled' => env('INTERCALL_HEARTBEAT_ENABLED', false),
        'timeout' => env('INTERCALL_HEARTBEAT_TIMEOUT', 2),
        'storage_path' => env('INTERCALL_HEARTBEAT_STORAGE_PATH', storage_path('intercall')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    |
    | Redis key prefix for intercall operations. When using Laravel's Redis
    | facade, set this to empty string since Laravel handles global prefixing
    | via config/database.php. For standalone GenericRedis, use 'intercall'.
    |
    */
    'redis' => [
        'prefix' => env('INTERCALL_REDIS_PREFIX', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency Settings
    |--------------------------------------------------------------------------
    |
    | Idempotency prevents duplicate processing of requests. When enabled,
    | the system caches request results for a configured TTL. If the same
    | request_id is received multiple times, the cached result is returned.
    |
    | This enables safe retry behavior in transport chain fallback scenarios.
    |
    */
    'idempotency' => [
        'enabled' => env('INTERCALL_IDEMPOTENCY_ENABLED', true),
        'ttl' => env('INTERCALL_IDEMPOTENCY_TTL', 3600),
        'prefix' => env('INTERCALL_IDEMPOTENCY_PREFIX', 'intercall:idempotency'),
    ],

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
        'endpoint' => env('INTERCALL_HTTP_ENDPOINT', '/intercall'),
        'route_name' => env('INTERCALL_HTTP_ROUTE_NAME', 'intecall.handle'),
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

    /*
    |--------------------------------------------------------------------------
    | File Watching (Development)
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic worker restarts when using --watch flag.
    | Only PHP files are monitored for changes.
    |
    */
    'watch' => [
        'paths' => [
            'app',
        ],

        'ignore' => [
            '*/tests/*',
            '*/Test.php',
            '*Test.php',
            '*.test.php',
        ],

        'poll_interval' => 1,

        'restart_delay' => 1,
    ],
];
