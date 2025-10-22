<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Bridge;

use DeployTeam\Intercall\Contracts\Bridge\Redis;
use Illuminate\Support\Facades\Redis as LaravelRedisFacade;

class LaravelRedis implements Redis
{
    public function lpush(string $key, string $value): int|false
    {
        return LaravelRedisFacade::lpush($key, $value);
    }

    public function brpop(string|array $keys, int $timeout): ?array
    {
        $result = LaravelRedisFacade::brpop($keys, $timeout);
        return $result === null || $result === false ? null : $result;
    }

    public function blpop(string|array $keys, int $timeout): ?array
    {
        $result = LaravelRedisFacade::blpop($keys, $timeout);
        return $result === null || $result === false ? null : $result;
    }

    public function setex(string $key, int $ttl, string $value): bool
    {
        return (bool) LaravelRedisFacade::setex($key, $ttl, $value);
    }

    public function get(string $key): ?string
    {
        $result = LaravelRedisFacade::get($key);
        return $result === false || $result === null ? null : (string) $result;
    }

    public function exists(string $key): bool
    {
        return (bool) LaravelRedisFacade::exists($key);
    }

    public function incr(string $key): int
    {
        return LaravelRedisFacade::incr($key);
    }

    public function expire(string $key, int $ttl): bool
    {
        return (bool) LaravelRedisFacade::expire($key, $ttl);
    }

    public function ttl(string $key): int
    {
        return LaravelRedisFacade::ttl($key);
    }

    public function publish(string $channel, string $message): int
    {
        return LaravelRedisFacade::publish($channel, $message);
    }

    /**
     * @return array<int, string>
     */
    public function keys(string $pattern): array
    {
        $keys = LaravelRedisFacade::keys($pattern);
        if (!is_array($keys)) {
            return [];
        }

        $prefix = config('database.redis.default.prefix');
        if ($prefix === null || $prefix === '') {
            return $keys;
        }

        return array_map(function ($key) use ($prefix) {
            if (str_starts_with($key, $prefix)) {
                return substr($key, strlen($prefix));
            }
            return $key;
        }, $keys);
    }

    public function del(string $key): int
    {
        return (int) LaravelRedisFacade::del($key);
    }
}
