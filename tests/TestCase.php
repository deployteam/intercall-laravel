<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Tests;

use DeployTeam\IntercallLaravel\IntercallLaravelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            IntercallLaravelServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('intercall.auth.secret_key', 'test-secret-key-for-testing-only');
        config()->set('intercall.auth.token_ttl', 300);
        config()->set('intercall.redis.prefix', 'intercall_test');
        config()->set('intercall.compression.format', 'json');
        config()->set('intercall.rate_limit.max_requests', 1000);
        config()->set('intercall.rate_limit.burst_limit', 50);
        config()->set('intercall.http_fallback.enabled', true);
        config()->set('intercall.http_fallback.endpoint', '/api/intercall');
        config()->set('intercall.transport.default', 'redis');
        config()->set('intercall.transport.fallback_chain', ['redis', 'http']);

        config()->set('database.redis.client', 'phpredis');
        config()->set('database.redis.default', [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'database' => 0,
        ]);
    }
}
