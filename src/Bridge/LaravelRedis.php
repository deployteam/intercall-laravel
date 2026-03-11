<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Bridge;

use DeployTeam\Intercall\Contracts\Bridge\Redis;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis as LaravelRedisFacade;

class LaravelRedis implements Redis
{
    public function __construct(
        private readonly string $connection = 'default',
    ) {}

    private function redis(): Connection
    {
        return LaravelRedisFacade::connection($this->connection);
    }

    private function reconnect(): void
    {
        try {
            $this->redis()->disconnect();
        } catch (\Throwable) {
        }

        LaravelRedisFacade::purge($this->connection);
    }

    public function lpush(string $key, string $value): int|false
    {
        $result = $this->redis()->lpush($key, $value);

        if ($result === false) {
            $this->reconnect();
            return $this->redis()->lpush($key, $value);
        }

        return $result;
    }

    public function brpop(string|array $keys, int $timeout): ?array
    {
        $result = $this->redis()->brpop($keys, $timeout);
        return $result === null || $result === false ? null : $result;
    }

    public function blpop(string|array $keys, int $timeout): ?array
    {
        $result = $this->redis()->blpop($keys, $timeout);
        return $result === null || $result === false ? null : $result;
    }

    public function setex(string $key, int $ttl, string $value): bool
    {
        $result = $this->redis()->setex($key, $ttl, $value);

        if ($result === false) {
            $this->reconnect();
            return (bool) $this->redis()->setex($key, $ttl, $value);
        }

        return (bool) $result;
    }

    public function get(string $key): ?string
    {
        $result = $this->redis()->get($key);
        return $result === false || $result === null ? null : (string) $result;
    }

    public function exists(string $key): bool
    {
        return (bool) $this->redis()->exists($key);
    }

    public function incr(string $key): int
    {
        $result = $this->redis()->incr($key);

        if ($result === false) {
            $this->reconnect();
            $result = $this->redis()->incr($key);
        }

        return (int) $result;
    }

    public function expire(string $key, int $ttl): bool
    {
        return (bool) $this->redis()->expire($key, $ttl);
    }

    public function ttl(string $key): int
    {
        return $this->redis()->ttl($key);
    }

    public function publish(string $channel, string $message): int
    {
        $result = $this->redis()->publish($channel, $message);

        if ($result === false) {
            $this->reconnect();
            return (int) $this->redis()->publish($channel, $message);
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    public function keys(string $pattern): array
    {
        $keys = $this->redis()->keys($pattern);
        if (!is_array($keys)) {
            return [];
        }

        $prefix = config("database.redis.{$this->connection}.prefix");
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
        return (int) $this->redis()->del($key);
    }

    public function disconnect(): void
    {
        $this->reconnect();
    }
}
