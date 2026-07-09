<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Http\Controllers;

use DeployTeam\Intercall\Configuration\SystemRegistry;
use DeployTeam\Intercall\Contracts\Bridge\EventDispatcher;
use DeployTeam\Intercall\Contracts\Bridge\JobDispatcher;
use DeployTeam\Intercall\Enums\AsyncStatus;
use DeployTeam\Intercall\Events\AsyncResponseReceived;
use DeployTeam\Intercall\Events\BaseIntercallEvent;
use DeployTeam\Intercall\Events\RequestReceived;
use DeployTeam\Intercall\Exceptions\Authentication\InvalidTokenException;
use DeployTeam\Intercall\Exceptions\Configuration\MissingTokenException;
use DeployTeam\Intercall\Exceptions\Configuration\SystemNotConfiguredException;
use DeployTeam\Intercall\Services\AsyncRequestManager;
use DeployTeam\Intercall\Services\EventRegistry;
use DeployTeam\Intercall\Services\IdempotencyManager;
use DeployTeam\Intercall\Services\IntercallAuth;
use DeployTeam\Intercall\Services\ListenerRegistry;
use DeployTeam\Intercall\Services\RateLimiter;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IntercallController extends Controller
{
    /** @param array<string, mixed> $config */
    public function __construct(
        protected EventRegistry $registry,
        protected IntercallAuth $auth,
        protected RateLimiter $rateLimiter,
        protected AsyncRequestManager $asyncManager,
        protected JobDispatcher $jobDispatcher,
        protected SystemRegistry $systemRegistry,
        protected IdempotencyManager $idempotency,
        protected EventDispatcher $eventDispatcher,
        protected ListenerRegistry $listenerRegistry,
        protected array $config,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $sourceSystem = $request->header('X-Intercall-Source');
            $eventName = $request->header('X-Intercall-Event');
            $isAsync = $request->header('X-Intercall-Async', 'false') === 'true';
            $requestId = $request->header('X-Intercall-Request-Id');
            $payload = $request->all();

            if (isset($payload['message_type']) && $payload['message_type'] === 'callback') {
                return $this->handleCallback($payload);
            }

            if (!$sourceSystem || !$eventName) {
                return $this->errorResponse(
                    'Missing required headers: X-Intercall-Source, X-Intercall-Event',
                    400,
                );
            }

            if ($this->config['auth']['enabled'] ?? true) {
                $token = $request->bearerToken();

                if (!$token) {
                    return $this->errorResponse('Missing authentication token', 401);
                }

                try {
                    $this->verifyTokenWithMultipleSecrets($token, $sourceSystem);
                } catch (Exception $e) {
                    return $this->errorResponse('Authentication failed: ' . $e->getMessage(), 401);
                }
            }

            if ($this->config['rate_limit']['enabled'] ?? true) {
                try {
                    $this->rateLimiter->attempt($sourceSystem);
                } catch (Exception $e) {
                    return $this->errorResponse(
                        $e->getMessage(),
                        429,
                        ['retry_after' => $this->rateLimiter->resetAt($sourceSystem) - time()],
                    );
                }
            }

            if ($requestId) {
                $cached = $this->idempotency->getCachedResponse($requestId);

                if ($cached !== null) {
                    if ($cached['error'] !== null) {
                        return $this->errorResponse($cached['error'], 500);
                    }

                    if ($isAsync) {
                        return response()->json([
                            'success' => true,
                            'request_id' => $requestId,
                            'status' => 'completed',
                            'result' => $cached['result'],
                        ]);
                    }

                    return response()->json([
                        'success' => true,
                        'result' => $cached['result'],
                    ]);
                }
            }

            $event = $this->reconstructEvent($eventName, $request->all());

            if ($event === null) {
                return $this->errorResponse("Event {$eventName} is not registered", 404);
            }

            event(new RequestReceived(
                $requestId ?? 'http-' . uniqid(),
                $sourceSystem,
                $eventName,
                $event,
            ));

            $handler = $this->registry->getHandler($eventName);

            if ($handler === null) {
                return $this->errorResponse("No handler registered for event: {$eventName}", 404);
            }

            if ($isAsync) {
                return $this->handleAsync($requestId ?? uniqid(), $event, $handler);
            }

            return $this->handleSync($requestId, $event, $handler);
        } catch (Exception $e) {
            $this->logError('HTTP request handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Internal error: ' . $e->getMessage(),
                500,
            );
        }
    }

    protected function handleSync(?string $requestId, mixed $event, mixed $handler): JsonResponse
    {
        try {
            $result = $handler->handle($event, ['http' => true]);

            if ($requestId) {
                $this->idempotency->cacheResponse($requestId, $result, null);
            }

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (Exception $e) {
            if ($requestId) {
                $this->idempotency->cacheResponse($requestId, null, $e->getMessage());
            }

            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    protected function handleAsync(string $requestId, mixed $event, mixed $handler): JsonResponse
    {
        try {
            $this->asyncManager->setStatus($requestId, AsyncStatus::PROCESSING);

            try {
                $this->jobDispatcher->dispatch(function () use ($requestId, $event, $handler): void {
                    try {
                        $result = $handler->handle($event, ['request_id' => $requestId, 'http' => true]);

                        $this->asyncManager->setStatus($requestId, AsyncStatus::COMPLETED, $result);

                        $this->idempotency->cacheResponse($requestId, $result, null);
                    } catch (Exception $e) {
                        $this->asyncManager->setStatus($requestId, AsyncStatus::FAILED, [
                            'error' => $e->getMessage(),
                        ]);

                        $this->idempotency->cacheResponse($requestId, null, $e->getMessage());

                        $this->logError('Async handler failed', [
                            'request_id' => $requestId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
            } catch (Exception $jobException) {
                $this->logError('Failed to queue async job, executing synchronously', [
                    'request_id' => $requestId,
                    'error' => $jobException->getMessage(),
                ]);

                try {
                    $result = $handler->handle($event, ['request_id' => $requestId, 'http' => true]);

                    $this->asyncManager->setStatus($requestId, AsyncStatus::COMPLETED, $result);

                    $this->idempotency->cacheResponse($requestId, $result, null);
                } catch (Exception $e) {
                    $this->asyncManager->setStatus($requestId, AsyncStatus::FAILED, [
                        'error' => $e->getMessage(),
                    ]);

                    $this->idempotency->cacheResponse($requestId, null, $e->getMessage());

                    $this->logError('Async handler failed', [
                        'request_id' => $requestId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'status' => 'processing',
            ], 202);
        } catch (Exception $e) {
            $this->asyncManager->setStatus($requestId, AsyncStatus::FAILED, [
                'error' => $e->getMessage(),
            ]);

            $this->idempotency->cacheResponse($requestId, null, $e->getMessage());

            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /** @param array<string, mixed> $callbackData */
    protected function handleCallback(array $callbackData): JsonResponse
    {
        try {
            $requestId = $callbackData['request_id'] ?? 'unknown';
            $result = $callbackData['result'] ?? null;
            $success = $callbackData['success'] ?? false;

            $eventName = $callbackData['original_event_name'] ?? null;

            $responseEvent = null;
            if ($eventName !== null) {
                $responseEventClass = $this->registry->getAsyncMapping($eventName);

                if (
                    $responseEventClass !== null
                    && class_exists($responseEventClass)
                    && is_subclass_of($responseEventClass, BaseIntercallEvent::class)
                ) {
                    /** @var class-string<BaseIntercallEvent<array<string, mixed>>> $responseEventClass */
                    $responseEvent = $responseEventClass::fromArray([
                        'payload' => is_array($result) ? $result : ['data' => $result],
                    ]);
                }
            }

            $this->eventDispatcher->dispatch(new AsyncResponseReceived(
                $requestId,
                $eventName ?? 'unknown',
                $responseEvent ?? $result,
                $success,
            ));

            return response()->json([
                'success' => true,
                'message' => 'Callback received',
            ]);
        } catch (Exception $e) {
            $this->logError('Failed to process callback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to process callback: ' . $e->getMessage(), 500);
        }
    }

    /** @param array<string, mixed> $payload */
    protected function reconstructEvent(string $eventName, array $payload): mixed
    {
        $eventClass = $this->registry->getEventClass($eventName);

        if ($eventClass !== null && class_exists($eventClass)) {
            return $eventClass::fromArray(['payload' => $payload]);
        }

        return new class ($payload, $eventName) extends BaseIntercallEvent {
            public function __construct(array $payload, private readonly string $name)
            {
                parent::__construct($payload);
            }

            public function getEventName(): string
            {
                return $this->name;
            }
        };
    }

    /**
     * @throws MissingTokenException
     * @throws InvalidTokenException
     */
    protected function verifyTokenWithMultipleSecrets(string $token, string $sourceSystem): void
    {
        $localConfig = $this->systemRegistry->getLocalSystemConfig();
        $tokens = $localConfig->getTokensForSystem($sourceSystem);

        if (count($tokens) === 0) {
            $allTokens = $localConfig->getTokens();
            $whitelists = array_map(
                static fn($tokenObj) => is_array($tokenObj->whitelist) ? $tokenObj->whitelist : [$tokenObj->whitelist],
                $allTokens,
            );

            logger()->warning('[Intercall HTTP] MissingTokenException diagnostic', [
                'source_system' => $sourceSystem,
                'source_system_bytes' => bin2hex($sourceSystem),
                'local_system_name' => $localConfig->name,
                'total_tokens_in_registry' => count($allTokens),
                'whitelists_per_token' => $whitelists,
                'system_registry_hash' => spl_object_hash($this->systemRegistry),
                'local_config_hash' => spl_object_hash($localConfig),
                'pid' => getmypid(),
            ]);

            throw MissingTokenException::forInboundSystem($sourceSystem);
        }

        $lastException = null;

        foreach ($tokens as $tokenObj) {
            try {
                $this->auth->verifyToken($token, $tokenObj->value);
                return;
            } catch (InvalidTokenException $e) {
                $lastException = $e;
                continue;
            }
        }

        throw $lastException ?? new InvalidTokenException('Authentication failed');
    }

    /** @param array<string, mixed> $extra */
    protected function errorResponse(
        string $message,
        int $status = 500,
        array $extra = [],
    ): JsonResponse {
        return response()->json(array_merge([
            'success' => false,
            'error' => $message,
        ], $extra), $status);
    }

    /** @param array<string, mixed> $context */
    protected function logError(string $message, array $context = []): void
    {
        if ($this->config['logging']['enabled'] ?? true) {
            logger()->channel($this->config['logging']['channel'] ?? 'stack')
                ->error("[Intercall HTTP] {$message}", $context);
        }
    }

    public function heartbeat(Request $request): JsonResponse
    {
        try {
            if ($this->config['auth']['enabled'] ?? true) {
                $sourceSystem = $request->header('X-Intercall-Source');
                $token = $request->bearerToken();

                if (!$sourceSystem) {
                    return $this->errorResponse('Missing required header: X-Intercall-Source', 400);
                }

                if (!$token) {
                    return $this->errorResponse('Missing authentication token', 401);
                }

                try {
                    $this->verifyTokenWithMultipleSecrets($token, $sourceSystem);
                } catch (Exception $e) {
                    return $this->errorResponse('Authentication failed: ' . $e->getMessage(), 401);
                }
            }

            return response()->json($this->listenerRegistry->getHeartbeatData());
        } catch (Exception $e) {
            return $this->errorResponse('Heartbeat check failed: ' . $e->getMessage(), 500);
        }
    }
}
