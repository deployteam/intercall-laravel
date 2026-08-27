<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Services;

use DeployTeam\Intercall\Services\RequestListener;
use DeployTeam\Intercall\Transports\Contracts\InboundTransport;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;

/**
 * Laravel-specific extension of RequestListener that treats each envelope as a
 * complete request lifecycle: it fires the application's terminating callbacks
 * (so scoped resources can flush their pending state) and then discards the
 * scoped container bindings.
 *
 * Without the terminate() call, long-running workers accumulate state in scoped
 * services (buffers, memoized repositories, in-process caches) that is silently
 * discarded when the next envelope resets the scope.
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
            if ($this->container instanceof Application) {
                $this->container->terminate();
            }
            $this->container?->forgetScopedInstances();
        }
    }
}
