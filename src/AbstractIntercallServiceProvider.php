<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel;

use DeployTeam\Intercall\Configuration\LocalSystemConfig;
use DeployTeam\Intercall\Transports\TransportChain;
use DeployTeam\Intercall\Intercall;
use DeployTeam\Intercall\Services\EventRegistry;
use DeployTeam\Intercall\Transports\Configuration\HttpOutboundConfig;
use DeployTeam\Intercall\Transports\Configuration\RedisInboundConfig;
use DeployTeam\Intercall\Transports\Configuration\RedisOutboundConfig;
use DeployTeam\Intercall\Transports\Factories\TransportFactory;
use Illuminate\Support\ServiceProvider;
use LogicException;

abstract class AbstractIntercallServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /** @var TransportFactory $transportFactory */
        $transportFactory = $this->app->make(TransportFactory::class);

        $this->registerCurrentSystem($transportFactory);
        $this->registerRemoteSystems($transportFactory);
        $this->registerEventHandlers();
        $this->registerCallbackHandlers();
    }

    /**
     * @return array<int, class-string>
     */
    abstract protected function eventHandlers(): array;

    /** @return array<int, class-string> */
    protected function callbackHandlers(): array
    {
        return [];
    }

    protected function registerCurrentSystem(TransportFactory $transportFactory): LocalSystemConfig
    {
        $transports = new TransportChain();

        foreach (config('intercall.current-system.transports') as $listener) {
            $transports->register(match ($listener['driver']) {
                'redis' => $transportFactory->createInbound(RedisInboundConfig::fromArray($listener['config'])),
                default => throw new LogicException("Inbound transport driver {$listener['driver']} not supported"),
            });
        }

        $system = Intercall::registerLocalSystem(
            config('intercall.current-system.name'),
            $transports
        );

        if (config('intercall.current-system.tokens')) {
            foreach (config('intercall.current-system.tokens') as $tokenConfig) {
                $system->registerToken(
                    $tokenConfig['secret'],
                    $tokenConfig['systems'] ?? '*'
                );
            }
        }

        return $system;
    }

    protected function registerRemoteSystems(TransportFactory $transportFactory): void
    {
        foreach (config('intercall.systems') as $data) {
            $name = $data['name'] ?? null;
            $token = $data['token'] ?? null;
            $transportConfig = $data['transports'] ?? [];

            if ($name === null) {
                throw new LogicException('A remote system needs a name');
            }

            if ($token === null) {
                throw new LogicException('A remote system needs a token');
            }

            if ($transportConfig === []) {
                throw new LogicException('A remote system needs at least one transport defined');
            }

            $transports = new TransportChain;

            foreach ($transportConfig as $transport) {
                $transports->register(match($transport['driver']) {
                    'redis' => $transportFactory->createOutbound(RedisOutboundConfig::fromArray($transport['config'])),
                    'http' => $transportFactory->createOutbound(HttpOutboundConfig::fromArray($transport['config'])),
                    default => throw new LogicException("Outbound transport driver {$transport['driver']} not supported"),
                });
            }

            Intercall::registerRemoteSystem($name, $token, $transports);
        }
    }

    protected function registerEventHandlers(): void
    {
        $handlers = $this->eventHandlers();

        if ($handlers === []) {
            return;
        }

        /** @var EventRegistry $registry */
        $registry = $this->app->make(EventRegistry::class);

        foreach ($handlers as $handlerClass) {
            $registry->register($handlerClass);
        }
    }

    protected function registerCallbackHandlers(): void
    {
        $handlers = $this->callbackHandlers();

        if ($handlers === []) {
            return;
        }

        /** @var EventRegistry $registry */
        $registry = $this->app->make(EventRegistry::class);

        foreach ($handlers as $handlerClass) {
            $registry->registerCallbackHandler($handlerClass);
        }
    }
}
