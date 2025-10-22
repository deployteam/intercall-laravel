<?php

// phpcs:disable Squiz.Classes.ClassFileName.NoMatch

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Tests\Unit\Services;

use DeployTeam\Intercall\Contracts\EventHandler;
use DeployTeam\Intercall\Contracts\IntercallEvent;
use DeployTeam\Intercall\Events\BaseIntercallEvent;
use DeployTeam\Intercall\Exceptions\Events\HandlerNotFoundException;
use DeployTeam\Intercall\Services\EventRegistry;
use DeployTeam\IntercallLaravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TestEvent extends BaseIntercallEvent
{
    public function getEventName(): string
    {
        return 'test.event';
    }
}

class AnotherTestEvent extends BaseIntercallEvent
{
    public function getEventName(): string
    {
        return 'another.test.event';
    }
}

class StringIdentifierHandler implements EventHandler
{
    public function handles(): string
    {
        return 'test.event';
    }

    public function handle(IntercallEvent $event, array $context = []): mixed
    {
        return ['handled' => true];
    }
}

class ClassStringHandler implements EventHandler
{
    public function handles(): string
    {
        return AnotherTestEvent::class;
    }

    public function handle(IntercallEvent $event, array $context = []): mixed
    {
        return ['handled_via_class' => true];
    }
}

final class EventRegistryTest extends TestCase
{
    private EventRegistry $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = app(EventRegistry::class);
    }

    #[Test]
    public function registersHandlerWithStringIdentifier(): void
    {
        $this->sut->register(StringIdentifierHandler::class);

        static::assertTrue($this->sut->hasHandler('test.event'));
        $handler = $this->sut->getHandler('test.event');
        static::assertInstanceOf(StringIdentifierHandler::class, $handler);
    }

    #[Test]
    public function registersHandlerWithClassString(): void
    {
        $this->sut->register(ClassStringHandler::class);

        static::assertTrue($this->sut->hasHandler('another.test.event'));
        $handler = $this->sut->getHandler('another.test.event');
        static::assertInstanceOf(ClassStringHandler::class, $handler);
    }

    #[Test]
    public function automaticallyRegistersEventClassWhenUsingClassString(): void
    {
        $this->sut->register(ClassStringHandler::class);

        $eventClass = $this->sut->getEventClass('another.test.event');
        static::assertSame(AnotherTestEvent::class, $eventClass);
    }

    #[Test]
    public function throwsExceptionForNonExistentHandler(): void
    {
        $this->expectException(HandlerNotFoundException::class);
        $this->expectExceptionMessage("No handler registered for event 'non.existent'");

        $this->sut->getHandler('non.existent');
    }

    #[Test]
    public function checksIfHandlerExists(): void
    {
        static::assertFalse($this->sut->hasHandler('non.existent'));
    }

    #[Test]
    public function registersMultipleHandlers(): void
    {
        $this->sut->register(StringIdentifierHandler::class);
        $this->sut->register(ClassStringHandler::class);

        static::assertTrue($this->sut->hasHandler('test.event'));
        static::assertTrue($this->sut->hasHandler('another.test.event'));

        $events = $this->sut->getRegisteredEvents();
        static::assertContains('test.event', $events);
        static::assertContains('another.test.event', $events);
    }

    #[Test]
    public function registersEventClassExplicitly(): void
    {
        $this->sut->registerEventClass(TestEvent::class);

        $eventClass = $this->sut->getEventClass('test.event');
        static::assertSame(TestEvent::class, $eventClass);
    }

    #[Test]
    public function mapsAsyncResponse(): void
    {
        $this->sut->mapAsyncResponse('request.event', TestEvent::class);

        $mapping = $this->sut->getAsyncMapping('request.event');
        static::assertSame(TestEvent::class, $mapping);
    }

    #[Test]
    public function returnsNullForNonExistentAsyncMapping(): void
    {
        $mapping = $this->sut->getAsyncMapping('non.existent');

        static::assertNull($mapping);
    }
}
