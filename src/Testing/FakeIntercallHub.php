<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Testing;

use Closure;
use DeployTeam\Intercall\Contracts\IntercallEvent;
use DeployTeam\Intercall\Contracts\IntercallHubContract;
use DeployTeam\Intercall\Services\RequestBuilder;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;

class FakeIntercallHub implements IntercallHubContract
{
    /** @var array<int, array{type: string, target: string, event: IntercallEvent<array<string, mixed>>}> */
    protected array $dispatched = [];

    /** @var array<string, mixed> */
    protected array $fakeResponses = [];

    /** @var mixed */
    protected mixed $defaultResponse = [];

    /** @param IntercallEvent<array<string, mixed>> $event */
    public function dispatch(string $targetSystem, IntercallEvent $event): mixed
    {
        $this->dispatched[] = ['type' => 'sync', 'target' => $targetSystem, 'event' => $event];

        return $this->getResponse($event->getEventName());
    }

    /** @param IntercallEvent<array<string, mixed>> $event */
    public function dispatchAsync(string $targetSystem, IntercallEvent $event): string
    {
        $this->dispatched[] = ['type' => 'async', 'target' => $targetSystem, 'event' => $event];

        return Str::uuid()->toString();
    }

    /** @param IntercallEvent<array<string, mixed>> $event */
    public function dispatchForget(string $targetSystem, IntercallEvent $event): string
    {
        $this->dispatched[] = ['type' => 'forget', 'target' => $targetSystem, 'event' => $event];

        return Str::uuid()->toString();
    }

    /**
     * @param array<int, string> $targets
     * @param IntercallEvent<array<string, mixed>> $event
     * @return array<string, mixed>
     */
    public function dispatchToMany(array $targets, IntercallEvent $event): array
    {
        $results = [];

        foreach ($targets as $target) {
            $results[$target] = $this->dispatch($target, $event);
        }

        return $results;
    }

    /**
     * @param IntercallEvent<array<string, mixed>> $event
     * @return array<string, mixed>
     */
    public function broadcast(IntercallEvent $event): array
    {
        $this->dispatched[] = ['type' => 'broadcast', 'target' => '*', 'event' => $event];

        return [];
    }

    /** @return array<string, mixed>|null */
    public function wait(string $requestId, int $timeout = 30): ?array
    {
        return [];
    }

    /** @return array<string, mixed>|null */
    public function status(string $requestId): ?array
    {
        return null;
    }

    public function to(string $targetSystem): RequestBuilder
    {
        return new RequestBuilder($this, $targetSystem);
    }

    public function fakeResponse(string $eventName, mixed $response): static
    {
        $this->fakeResponses[$eventName] = $response;

        return $this;
    }

    public function setDefaultResponse(mixed $response): static
    {
        $this->defaultResponse = $response;

        return $this;
    }

    /** @return array<int, array{type: string, target: string, event: IntercallEvent<array<string, mixed>>}> */
    public function getDispatched(): array
    {
        return $this->dispatched;
    }

    /**
     * @param (Closure(IntercallEvent<array<string, mixed>>, string): bool)|null $callback
     */
    public function assertDispatched(string $eventName, ?Closure $callback = null): void
    {
        $matching = $this->findDispatched($eventName, $callback);

        Assert::assertNotEmpty(
            $matching,
            "The expected event [{$eventName}] was not dispatched.",
        );
    }

    /**
     * @param (Closure(IntercallEvent<array<string, mixed>>, string): bool)|null $callback
     */
    public function assertNotDispatched(string $eventName, ?Closure $callback = null): void
    {
        $matching = $this->findDispatched($eventName, $callback);

        Assert::assertEmpty(
            $matching,
            "The unexpected event [{$eventName}] was dispatched.",
        );
    }

    public function assertDispatchedCount(int $count): void
    {
        Assert::assertCount(
            $count,
            $this->dispatched,
            "Expected {$count} dispatched event(s), but " . count($this->dispatched) . ' were dispatched.',
        );
    }

    public function assertNothingDispatched(): void
    {
        Assert::assertEmpty(
            $this->dispatched,
            count($this->dispatched) . ' unexpected event(s) were dispatched.',
        );
    }

    public function assertDispatchedTo(string $eventName, string $targetSystem): void
    {
        $matching = $this->findDispatched(
            $eventName,
            fn(IntercallEvent $event, string $target): bool => $target === $targetSystem,
        );

        Assert::assertNotEmpty(
            $matching,
            "The expected event [{$eventName}] was not dispatched to [{$targetSystem}].",
        );
    }

    protected function getResponse(string $eventName): mixed
    {
        if (array_key_exists($eventName, $this->fakeResponses)) {
            $response = $this->fakeResponses[$eventName];

            if ($response instanceof Closure) {
                return $response();
            }

            return $response;
        }

        if ($this->defaultResponse instanceof Closure) {
            return ($this->defaultResponse)();
        }

        return $this->defaultResponse;
    }

    /**
     * @param (Closure(IntercallEvent<array<string, mixed>>, string): bool)|null $callback
     * @return array<int, array{type: string, target: string, event: IntercallEvent<array<string, mixed>>}>
     */
    protected function findDispatched(string $eventName, ?Closure $callback = null): array
    {
        return array_filter(
            $this->dispatched,
            function (array $entry) use ($eventName, $callback): bool {
                if ($entry['event']->getEventName() !== $eventName) {
                    return false;
                }

                if ($callback !== null) {
                    return $callback($entry['event'], $entry['target']);
                }

                return true;
            },
        );
    }
}
