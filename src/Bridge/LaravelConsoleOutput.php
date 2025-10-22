<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Bridge;

use DeployTeam\Intercall\Contracts\Bridge\ConsoleOutput;
use Illuminate\Console\Command;

class LaravelConsoleOutput implements ConsoleOutput
{
    public function __construct(protected Command $command) {}

    public function info(string $message): void
    {
        $this->command->info($message);
    }

    public function error(string $message): void
    {
        $this->command->error($message);
    }

    public function warning(string $message): void
    {
        $this->command->warn($message);
    }

    public function newLine(int $count = 1): void
    {
        $this->command->newLine($count);
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->command->option($key) ?? $default;
    }
}
