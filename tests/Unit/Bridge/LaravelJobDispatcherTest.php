<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Tests\Unit\Bridge;

use DeployTeam\IntercallLaravel\Bridge\LaravelJobDispatcher;
use DeployTeam\IntercallLaravel\Tests\TestCase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;

final class LaravelJobDispatcherTest extends TestCase
{
    private LaravelJobDispatcher $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new LaravelJobDispatcher();
    }

    #[Test]
    public function dispatchesJob(): void
    {
        Bus::fake();
        $job = function (): void {};

        $this->sut->dispatch($job);

        static::assertTrue(true);
    }
}
