<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Tests\Unit\Bridge;

use DeployTeam\IntercallLaravel\Bridge\LaravelHttpClient;
use DeployTeam\IntercallLaravel\Bridge\LaravelHttpResponse;
use DeployTeam\IntercallLaravel\Tests\TestCase;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response as LaravelResponse;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

final class LaravelHttpClientTest extends TestCase
{
    private LaravelHttpClient $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new LaravelHttpClient();
    }

    #[Test]
    public function makesRequestWithJsonData(): void
    {
        $mockRequest = Mockery::mock(PendingRequest::class);
        $mockResponse = Mockery::mock(LaravelResponse::class);
        Http::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturn($mockRequest);
        $mockRequest->shouldReceive('withHeaders')
            ->once()
            ->with(['Content-Type' => 'application/json'])
            ->andReturnSelf();
        $mockRequest->shouldReceive('send')
            ->once()
            ->with('POST', 'http://example.com/api', ['json' => ['key' => 'value']])
            ->andReturn($mockResponse);

        $result = $this->sut->request('POST', 'http://example.com/api', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['key' => 'value'],
        ]);

        static::assertInstanceOf(LaravelHttpResponse::class, $result);
    }

    #[Test]
    public function makesRequestWithCustomTimeout(): void
    {
        $mockRequest = Mockery::mock(PendingRequest::class);
        $mockResponse = Mockery::mock(LaravelResponse::class);
        Http::shouldReceive('timeout')
            ->once()
            ->with(60)
            ->andReturn($mockRequest);
        $mockRequest->shouldReceive('send')
            ->once()
            ->andReturn($mockResponse);

        $result = $this->sut->request('GET', 'http://example.com/api', [
            'timeout' => 60,
        ]);

        static::assertInstanceOf(LaravelHttpResponse::class, $result);
    }

    #[Test]
    public function makesRequestWithoutJson(): void
    {
        $mockRequest = Mockery::mock(PendingRequest::class);
        $mockResponse = Mockery::mock(LaravelResponse::class);
        Http::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturn($mockRequest);
        $mockRequest->shouldReceive('send')
            ->once()
            ->with('GET', 'http://example.com/api', [])
            ->andReturn($mockResponse);

        $result = $this->sut->request('GET', 'http://example.com/api');

        static::assertInstanceOf(LaravelHttpResponse::class, $result);
    }
}
