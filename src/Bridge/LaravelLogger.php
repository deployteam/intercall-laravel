<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Bridge;

use DeployTeam\Intercall\Contracts\Bridge\Logger;
use DeployTeam\Intercall\Enums\LogLevel;
use Illuminate\Support\Facades\Log;

class LaravelLogger implements Logger
{
    private LogLevel $minimumLevel = LogLevel::INFO;

    /**
     * @param string|null $channel
     */
    public function __construct(protected ?string $channel = null) {}

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        if (LogLevel::INFO->shouldLog($this->minimumLevel)) {
            $this->getLogger()->info($message, $context);
        }
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        if (LogLevel::ERROR->shouldLog($this->minimumLevel)) {
            $this->getLogger()->error($message, $context);
        }
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        if (LogLevel::WARNING->shouldLog($this->minimumLevel)) {
            $this->getLogger()->warning($message, $context);
        }
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        if (LogLevel::DEBUG->shouldLog($this->minimumLevel)) {
            $this->getLogger()->debug($message, $context);
        }
    }

    /**
     * @param LogLevel $level
     */
    public function setMinimumLevel(LogLevel $level): void
    {
        $this->minimumLevel = $level;
    }

    /**
     * @return LogLevel
     */
    public function getMinimumLevel(): LogLevel
    {
        return $this->minimumLevel;
    }

    /**
     * @return mixed
     */
    protected function getLogger(): mixed
    {
        return $this->channel
            ? Log::channel($this->channel)
            : Log::getFacadeRoot();
    }
}
