<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Tests\Unit\Console;

use DeployTeam\IntercallLaravel\Console\ListenCommand;
use DeployTeam\IntercallLaravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ListenCommandTest extends TestCase
{
    #[Test]
    public function hasCorrectSignature(): void
    {
        $sut = $this->app->make(ListenCommand::class);

        static::assertSame('intercall:listen', $sut->getName());
    }

    #[Test]
    public function hasCorrectDescription(): void
    {
        $sut = $this->app->make(ListenCommand::class);

        static::assertSame('Listen for incoming inter-system requests', $sut->getDescription());
    }

    #[Test]
    public function hasWorkersOption(): void
    {
        $sut = $this->app->make(ListenCommand::class);

        $definition = $sut->getDefinition();

        static::assertTrue($definition->hasOption('workers'));
        static::assertSame('1', $definition->getOption('workers')->getDefault());
    }

    #[Test]
    public function canBeResolvedFromContainer(): void
    {
        $sut = $this->app->make(ListenCommand::class);

        static::assertInstanceOf(ListenCommand::class, $sut);
    }
}
