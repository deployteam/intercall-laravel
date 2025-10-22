<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel;

use DeployTeam\Intercall\Configuration\SystemRegistry;
use DeployTeam\Intercall\Contracts\Bridge\EventDispatcher;
use DeployTeam\Intercall\Contracts\Bridge\HttpClient;
use DeployTeam\Intercall\Contracts\Bridge\JobDispatcher;
use DeployTeam\Intercall\Contracts\Bridge\Logger;
use DeployTeam\Intercall\Contracts\Bridge\Redis as IntercallRedis;
use DeployTeam\Intercall\Enums\LogLevel;
use DeployTeam\Intercall\Events\AsyncResponseReceived;
use DeployTeam\Intercall\Intercall;
use DeployTeam\Intercall\Listeners\AsyncEventResponseListener;
use DeployTeam\Intercall\Services\AsyncRequestManager;
use DeployTeam\Intercall\Services\EventRegistry;
use DeployTeam\Intercall\Services\HeartbeatChecker;
use DeployTeam\Intercall\Services\IdempotencyManager;
use DeployTeam\Intercall\Services\IntercallAuth;
use DeployTeam\Intercall\Services\IntercallHub;
use DeployTeam\Intercall\Services\ListenerRegistry;
use DeployTeam\Intercall\Services\MessageSerializer;
use DeployTeam\Intercall\Services\RateLimiter;
use DeployTeam\Intercall\Services\RequestDispatcher;
use DeployTeam\Intercall\Services\RequestListener;
use DeployTeam\Intercall\Services\TransportManager;
use DeployTeam\Intercall\Transports\Factories\HttpOutboundTransportFactory;
use DeployTeam\Intercall\Transports\Factories\RedisInboundTransportFactory;
use DeployTeam\Intercall\Transports\Factories\RedisOutboundTransportFactory;
use DeployTeam\Intercall\Transports\Factories\TransportFactory;
use DeployTeam\IntercallLaravel\Bridge\LaravelContainer;
use DeployTeam\IntercallLaravel\Bridge\LaravelEventDispatcher;
use DeployTeam\IntercallLaravel\Bridge\LaravelHttpClient;
use DeployTeam\IntercallLaravel\Bridge\LaravelJobDispatcher;
use DeployTeam\IntercallLaravel\Bridge\LaravelLogger;
use DeployTeam\IntercallLaravel\Bridge\LaravelRedis;
use DeployTeam\IntercallLaravel\Console\ListenCommand;
use DeployTeam\IntercallLaravel\Http\Controllers\IntercallController;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IntercallLaravelServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('intercall')
            ->hasConfigFile()
            ->hasCommands([
                ListenCommand::class,
            ]);

        if (config('intercall.http_fallback.enabled', true)) {
            $package->hasRoute('intercall');
        }
    }

    public function bootingPackage(): void
    {
        Event::listen(AsyncResponseReceived::class, function (AsyncResponseReceived $event) {
            $listener = $this->app->make(AsyncEventResponseListener::class);
            $listener->handle($event);
        });
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(SystemRegistry::class, function (): SystemRegistry {
            $registry = new SystemRegistry();
            Intercall::init($registry);
            return $registry;
        });

        $this->app->make(SystemRegistry::class);

        $this->app->singleton(IntercallRedis::class, function (): LaravelRedis {
            return new LaravelRedis();
        });

        $this->app->singleton(Logger::class, function (): LaravelLogger {
            $logger = new LaravelLogger(config('intercall.logging.channel'));

            $logLevel = LogLevel::fromString(
                config('intercall.logging.level', 'info'),
            );
            $logger->setMinimumLevel($logLevel);

            return $logger;
        });

        $this->app->singleton(EventDispatcher::class, function (): LaravelEventDispatcher {
            return new LaravelEventDispatcher();
        });

        $this->app->singleton(HttpClient::class, function (): LaravelHttpClient {
            return new LaravelHttpClient();
        });

        $this->app->singleton(JobDispatcher::class, function (): LaravelJobDispatcher {
            return new LaravelJobDispatcher();
        });

        $this->app->singleton(EventRegistry::class, function ($app): EventRegistry {
            return new EventRegistry(new LaravelContainer($app));
        });

        $this->app->singleton(MessageSerializer::class, function ($app): MessageSerializer {
            $format = config('intercall.compression.format', 'msgpack');
            return new MessageSerializer($format);
        });

        $this->app->singleton(IntercallAuth::class, function ($app): IntercallAuth {
            return new IntercallAuth(
                $app->make(IntercallRedis::class),
                config('intercall.auth.token_ttl', 300),
                config('intercall.redis.prefix', 'intercall'),
            );
        });

        $this->app->singleton(RateLimiter::class, function ($app): RateLimiter {
            return new RateLimiter(
                $app->make(IntercallRedis::class),
                config('intercall.rate_limit.max_requests', 1000),
                config('intercall.rate_limit.burst_limit', 50),
                config('intercall.redis.prefix', 'intercall'),
            );
        });

        $this->app->singleton(AsyncRequestManager::class, function ($app): AsyncRequestManager {
            return new AsyncRequestManager(
                $app->make(IntercallRedis::class),
                config('intercall.async.status_ttl', 3600),
                config('intercall.redis.prefix', 'intercall'),
            );
        });

        $this->app->singleton(IdempotencyManager::class, function ($app): IdempotencyManager {
            return new IdempotencyManager(
                $app->make(IntercallRedis::class),
                $app->make(MessageSerializer::class),
                $app->make(Logger::class),
                config('intercall'),
            );
        });

        $this->app->singleton(ListenerRegistry::class, function ($app): ListenerRegistry {
            try {
                $redis = $app->make(IntercallRedis::class);
            } catch (\Throwable $e) {
                $redis = null;
            }

            return new ListenerRegistry(
                $app->make(SystemRegistry::class),
                $redis,
                config('intercall.redis.prefix', 'intercall'),
                config('intercall.heartbeat.storage_path'),
            );
        });

        $this->app->singleton(HeartbeatChecker::class, function ($app): HeartbeatChecker {
            return new HeartbeatChecker(
                $app->make(SystemRegistry::class),
                $app->make(Logger::class),
                $app->make(IntercallAuth::class),
                config('intercall'),
            );
        });

        $this->app->singleton(TransportFactory::class, function ($app): TransportFactory {
            return new TransportFactory(
                $app->make(RedisInboundTransportFactory::class),
                $app->make(RedisOutboundTransportFactory::class),
                $app->make(HttpOutboundTransportFactory::class),
            );
        });

        $this->app->singleton(TransportManager::class, function ($app): TransportManager {
            return new TransportManager(
                $app->make(Logger::class),
                config('intercall'),
                $app->make(TransportFactory::class),
                $app->make(SystemRegistry::class),
            );
        });

        $this->app->singleton(RequestDispatcher::class, function ($app): RequestDispatcher {
            return new RequestDispatcher(
                $app->make(TransportManager::class),
                $app->make(IntercallRedis::class),
                $app->make(Logger::class),
                $app->make(IntercallAuth::class),
                $app->make(RateLimiter::class),
                $app->make(AsyncRequestManager::class),
                $app->make(MessageSerializer::class),
                $app->make(SystemRegistry::class),
                $app->make(HeartbeatChecker::class),
                config('intercall'),
            );
        });

        $this->app->singleton(RequestListener::class, function ($app): RequestListener {
            return new RequestListener(
                $app->make(TransportManager::class),
                $app->make(Logger::class),
                $app->make(EventDispatcher::class),
                $app->make(IntercallAuth::class),
                $app->make(RateLimiter::class),
                $app->make(AsyncRequestManager::class),
                $app->make(MessageSerializer::class),
                $app->make(EventRegistry::class),
                $app->make(SystemRegistry::class),
                $app->make(IdempotencyManager::class),
                $app->make(ListenerRegistry::class),
                $app->make(HeartbeatChecker::class),
                config('intercall'),
            );
        });


        $this->app->singleton(IntercallHub::class, function ($app): IntercallHub {
            return new IntercallHub(
                $app->make(RequestDispatcher::class),
                $app->make(AsyncRequestManager::class),
                config('intercall'),
            );
        });

        $this->app->singleton(IntercallController::class, function ($app): IntercallController {
            return new IntercallController(
                $app->make(EventRegistry::class),
                $app->make(IntercallAuth::class),
                $app->make(RateLimiter::class),
                $app->make(AsyncRequestManager::class),
                $app->make(JobDispatcher::class),
                $app->make(SystemRegistry::class),
                $app->make(IdempotencyManager::class),
                $app->make(EventDispatcher::class),
                $app->make(ListenerRegistry::class),
                config('intercall'),
            );
        });
    }
}
