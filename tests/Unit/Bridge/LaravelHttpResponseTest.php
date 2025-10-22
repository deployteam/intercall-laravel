<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Tests\Unit\Bridge;

use DeployTeam\IntercallLaravel\Bridge\LaravelHttpResponse;
use DeployTeam\IntercallLaravel\Tests\TestCase;
use Illuminate\Http\Client\Response as LaravelResponse;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

final class LaravelHttpResponseTest extends TestCase
{
    #[Test]
    public function returnsStatusCode(): void
    {
        $laravelResponse = Mockery::mock(LaravelResponse::class);
        $laravelResponse->shouldReceive('status')
            ->once()
            ->andReturn(200);

        $sut = new LaravelHttpResponse($laravelResponse);

        static::assertSame(200, $sut->getStatusCode());
    }

    #[Test]
    public function returnsResponseBody(): void
    {
        $laravelResponse = Mockery::mock(LaravelResponse::class);
        $laravelResponse->shouldReceive('body')
            ->once()
            ->andReturn('{"success": true}');

        $sut = new LaravelHttpResponse($laravelResponse);

        static::assertSame('{"success": true}', $sut->getBody());
    }

    #[Test]
    public function returnsJsonAsArray(): void
    {
        $laravelResponse = Mockery::mock(LaravelResponse::class);
        $laravelResponse->shouldReceive('json')
            ->once()
            ->andReturn(['success' => true]);

        $sut = new LaravelHttpResponse($laravelResponse);

        static::assertSame(['success' => true], $sut->toArray());
    }

    #[Test]
    public function returnsEmptyArrayWhenJsonIsNull(): void
    {
        $laravelResponse = Mockery::mock(LaravelResponse::class);
        $laravelResponse->shouldReceive('json')
            ->once()
            ->andReturn(null);

        $sut = new LaravelHttpResponse($laravelResponse);

        static::assertSame([], $sut->toArray());
    }

    #[Test]
    public function returnsTrueForSuccessfulResponse(): void
    {
        $laravelResponse = Mockery::mock(LaravelResponse::class);
        $laravelResponse->shouldReceive('successful')
            ->once()
            ->andReturn(true);

        $sut = new LaravelHttpResponse($laravelResponse);

        static::assertTrue($sut->isSuccessful());
    }

    #[Test]
    public function returnsFalseForFailedResponse(): void
    {
        $laravelResponse = Mockery::mock(LaravelResponse::class);
        $laravelResponse->shouldReceive('successful')
            ->once()
            ->andReturn(false);

        $sut = new LaravelHttpResponse($laravelResponse);

        static::assertFalse($sut->isSuccessful());
    }
}
