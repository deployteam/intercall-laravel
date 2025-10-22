<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Tests\Unit\Console;

use DeployTeam\IntercallLaravel\Console\CallbackCommand;
use DeployTeam\IntercallLaravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class CallbackCommandTest extends TestCase
{
    #[Test]
    public function hasCorrectSignature(): void
    {
        $sut = $this->app->make(CallbackCommand::class);

        static::assertSame('intercall:callbacks', $sut->getName());
    }

    #[Test]
    public function hasCorrectDescription(): void
    {
        $sut = $this->app->make(CallbackCommand::class);

        static::assertSame('Listen for async response callbacks', $sut->getDescription());
    }

    #[Test]
    public function hasWorkersOption(): void
    {
        $sut = $this->app->make(CallbackCommand::class);

        $definition = $sut->getDefinition();

        static::assertTrue($definition->hasOption('workers'));
        static::assertSame('1', $definition->getOption('workers')->getDefault());
    }

    #[Test]
    public function canBeResolvedFromContainer(): void
    {
        $sut = $this->app->make(CallbackCommand::class);

        static::assertInstanceOf(CallbackCommand::class, $sut);
    }
}
