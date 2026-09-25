<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Bridge;

use DeployTeam\Intercall\Contracts\Bridge\Redis;
use DeployTeam\Intercall\Exceptions\Transport\RedisConnectionException;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis as LaravelRedisFacade;
use RedisException;
use Throwable;

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
        } catch (Throwable) {
        }

        LaravelRedisFacade::purge($this->connection);
    }

    /**
     * @template TReturn
     * @param callable(): TReturn $operation
     * @return TReturn
     */
    private function executeWithRetry(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (Throwable $exception) {
            if (!$this->isTransientConnectionFailure($exception)) {
                throw $exception;
            }

            $this->reconnect();

            return $operation();
        }
    }

    private function isTransientConnectionFailure(Throwable $exception): bool
    {
        if ($exception instanceof RedisException) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        foreach ([
            'read error on connection',
            'connection lost',
            'connection refused',
            'connection reset',
            'connection timed out',
            'went away',
            'socket error',
            'error while reading',
            'readonly',
            'broken pipe',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function lpush(string $key, string $value): int|false
    {
        return $this->redis()->lpush($key, $value);
    }

    public function brpop(string|array $keys, int $timeout): ?array
    {
        return $this->blockingPop(fn(): mixed => $this->redis()->brpop($keys, $timeout));
    }

    public function blpop(string|array $keys, int $timeout): ?array
    {
        return $this->blockingPop(fn(): mixed => $this->redis()->blpop($keys, $timeout));
    }

    /**
     * A blocking pop is destructive, so a failed call is never re-issued: replaying it
     * could consume a second message. The connection is dropped instead, leaving any
     * unread reply behind with it, and the caller's next cycle starts on a fresh one.
     *
     * @param callable(): mixed $operation
     * @return array<int, string>|null
     */
    private function blockingPop(callable $operation): ?array
    {
        try {
            $result = $operation();
        } catch (Throwable $exception) {
            if (!$this->isTransientConnectionFailure($exception)) {
                throw $exception;
            }

            $this->reconnect();

            return null;
        }

        if ($result === false) {
            $this->reconnect();

            return null;
        }

        if (!is_array($result) || $result === []) {
            return null;
        }

        /** @var array<int, string> $result */
        return $result;
    }

    public function setex(string $key, int $ttl, string $value): bool
    {
        return $this->executeWithRetry(function () use ($key, $ttl, $value): bool {
            return (bool) $this->redis()->setex($key, $ttl, $value);
        });
    }

    public function get(string $key): ?string
    {
        return $this->executeWithRetry(function () use ($key): ?string {
            $result = $this->redis()->get($key);

            if ($result === false || $result === null) {
                return null;
            }

            if (!is_string($result)) {
                throw RedisConnectionException::unexpectedReplyType('GET', $key, get_debug_type($result));
            }

            return $result;
        });
    }

    public function exists(string $key): bool
    {
        return $this->executeWithRetry(function () use ($key): bool {
            return (bool) $this->redis()->exists($key);
        });
    }

    public function incr(string $key): int
    {
        try {
            $result = $this->redis()->incr($key);
        } catch (Throwable $exception) {
            if (!$this->isTransientConnectionFailure($exception)) {
                throw $exception;
            }
            $this->reconnect();
            $result = $this->redis()->incr($key);
        }

        if ($result === false) {
            $this->reconnect();
            $result = $this->redis()->incr($key);
        }

        if ($result === false) {
            throw new RedisException("INCR command failed for key {$key}");
        }

        return (int) $result;
    }

    public function expire(string $key, int $ttl): bool
    {
        return $this->executeWithRetry(function () use ($key, $ttl): bool {
            return (bool) $this->redis()->expire($key, $ttl);
        });
    }

    public function ttl(string $key): int
    {
        return $this->executeWithRetry(function () use ($key): int {
            $result = $this->redis()->ttl($key);
            if ($result === false) {
                throw new RedisException('TTL command returned false');
            }
            return (int) $result;
        });
    }

    public function publish(string $channel, string $message): int
    {
        return (int) $this->redis()->publish($channel, $message);
    }

    /**
     * @return array<int, string>
     */
    public function keys(string $pattern): array
    {
        return $this->executeWithRetry(function () use ($pattern): array {
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
        });
    }

    public function del(string $key): int
    {
        return $this->executeWithRetry(function () use ($key): int {
            return (int) $this->redis()->del($key);
        });
    }

    public function disconnect(): void
    {
        $this->reconnect();
    }
}
