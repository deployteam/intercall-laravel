<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Tests\Unit\Bridge;

use DeployTeam\IntercallLaravel\Bridge\LaravelConsoleOutput;
use DeployTeam\IntercallLaravel\Tests\TestCase;
use Illuminate\Console\Command;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

final class LaravelConsoleOutputTest extends TestCase
{
    private Command $command;
    private LaravelConsoleOutput $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = Mockery::mock(Command::class);
        $this->sut = new LaravelConsoleOutput($this->command);
    }

    #[Test]
    public function callsCommandInfo(): void
    {
        $this->command->shouldReceive('info')
            ->once()
            ->with('Info message');

        $this->sut->info('Info message');
    }

    #[Test]
    public function callsCommandError(): void
    {
        $this->command->shouldReceive('error')
            ->once()
            ->with('Error message');

        $this->sut->error('Error message');
    }

    #[Test]
    public function callsCommandWarn(): void
    {
        $this->command->shouldReceive('warn')
            ->once()
            ->with('Warning message');

        $this->sut->warning('Warning message');
    }

    #[Test]
    public function returnsOptionValue(): void
    {
        $this->command->shouldReceive('option')
            ->once()
            ->with('test-key')
            ->andReturn('test-value');

        $result = $this->sut->option('test-key');

        static::assertSame('test-value', $result);
    }

    #[Test]
    public function returnsDefaultWhenOptionIsNull(): void
    {
        $this->command->shouldReceive('option')
            ->once()
            ->with('test-key')
            ->andReturn(null);

        $result = $this->sut->option('test-key', 'default-value');

        static::assertSame('default-value', $result);
    }
}
