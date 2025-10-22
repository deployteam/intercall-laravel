<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Tests\Unit\Bridge;

use DeployTeam\IntercallLaravel\Bridge\LaravelRedis;
use DeployTeam\IntercallLaravel\Tests\TestCase;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;

final class LaravelRedisTest extends TestCase
{
    private LaravelRedis $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new LaravelRedis();
    }

    #[Test]
    public function pushesValueToList(): void
    {
        Redis::shouldReceive('lpush')
            ->once()
            ->with('test-key', 'test-value')
            ->andReturn(1);

        $result = $this->sut->lpush('test-key', 'test-value');

        static::assertSame(1, $result);
    }

    #[Test]
    public function returnsArrayOnBrpopSuccess(): void
    {
        Redis::shouldReceive('brpop')
            ->once()
            ->with('test-key', 10)
            ->andReturn(['test-key', 'test-value']);

        $result = $this->sut->brpop('test-key', 10);

        static::assertIsArray($result);
        static::assertSame(['test-key', 'test-value'], $result);
    }

    #[Test]
    public function returnsNullWhenBrpopEmpty(): void
    {
        Redis::shouldReceive('brpop')
            ->once()
            ->with('test-key', 10)
            ->andReturn(null);

        $result = $this->sut->brpop('test-key', 10);

        static::assertNull($result);
    }

    #[Test]
    public function setsValueWithTtl(): void
    {
        Redis::shouldReceive('setex')
            ->once()
            ->with('test-key', 300, 'test-value')
            ->andReturn(true);

        $result = $this->sut->setex('test-key', 300, 'test-value');

        static::assertTrue($result);
    }

    #[Test]
    public function getsValue(): void
    {
        Redis::shouldReceive('get')
            ->once()
            ->with('test-key')
            ->andReturn('test-value');

        $result = $this->sut->get('test-key');

        static::assertSame('test-value', $result);
    }

    #[Test]
    public function returnsNullWhenKeyNotFound(): void
    {
        Redis::shouldReceive('get')
            ->once()
            ->with('test-key')
            ->andReturn(false);

        $result = $this->sut->get('test-key');

        static::assertNull($result);
    }

    #[Test]
    public function returnsTrueWhenKeyExists(): void
    {
        Redis::shouldReceive('exists')
            ->once()
            ->with('test-key')
            ->andReturn(1);

        $result = $this->sut->exists('test-key');

        static::assertTrue($result);
    }

    #[Test]
    public function returnsFalseWhenKeyDoesNotExist(): void
    {
        Redis::shouldReceive('exists')
            ->once()
            ->with('test-key')
            ->andReturn(0);

        $result = $this->sut->exists('test-key');

        static::assertFalse($result);
    }

    #[Test]
    public function incrementsValue(): void
    {
        Redis::shouldReceive('incr')
            ->once()
            ->with('test-key')
            ->andReturn(5);

        $result = $this->sut->incr('test-key');

        static::assertSame(5, $result);
    }

    #[Test]
    public function setsTtl(): void
    {
        Redis::shouldReceive('expire')
            ->once()
            ->with('test-key', 300)
            ->andReturn(1);

        $result = $this->sut->expire('test-key', 300);

        static::assertTrue($result);
    }

    #[Test]
    public function returnsRemainingTime(): void
    {
        Redis::shouldReceive('ttl')
            ->once()
            ->with('test-key')
            ->andReturn(120);

        $result = $this->sut->ttl('test-key');

        static::assertSame(120, $result);
    }

    #[Test]
    public function publishesMessage(): void
    {
        Redis::shouldReceive('publish')
            ->once()
            ->with('test-channel', 'test-message')
            ->andReturn(1);

        $result = $this->sut->publish('test-channel', 'test-message');

        static::assertSame(1, $result);
    }
}
