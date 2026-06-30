<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Services;

use DeployTeam\Intercall\Services\RequestListener;
use DeployTeam\Intercall\Transports\Contracts\InboundTransport;
use Illuminate\Contracts\Container\Container;

/**
 * Laravel-specific extension of RequestListener that flushes scoped container
 * instances between envelope processings.
 *
 * Without this, long-running intercall workers reuse the same scoped bindings
 * across events, which leaks per-request state (repositories with property-level
 * memoization, in-process caches, etc.) and causes intermittent stale-data bugs.
 */
class LaravelRequestListener extends RequestListener
{
    private ?Container $container = null;

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    /**
     * @param array<string, mixed> $envelope
     */
    protected function processEnvelope(
        array $envelope,
        string $workerId,
        InboundTransport $transport,
    ): void {
        try {
            parent::processEnvelope($envelope, $workerId, $transport);
        } finally {
            $this->container?->forgetScopedInstances();
        }
    }
}
