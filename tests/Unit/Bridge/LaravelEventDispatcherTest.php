<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Tests\Unit\Bridge;

use DeployTeam\IntercallLaravel\Bridge\LaravelEventDispatcher;
use DeployTeam\IntercallLaravel\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;

final class LaravelEventDispatcherTest extends TestCase
{
    private LaravelEventDispatcher $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new LaravelEventDispatcher();
    }

    #[Test]
    public function dispatchesEvent(): void
    {
        Event::fake();
        $event = new class {
            public string $name = 'TestEvent';
        };

        $this->sut->dispatch($event);

        Event::assertDispatched($event::class);
    }
}
