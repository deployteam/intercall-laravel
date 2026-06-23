<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Console;

use DeployTeam\Intercall\Configuration\SystemRegistry;
use DeployTeam\Intercall\Console\ListenCommand as CoreListenCommand;
use DeployTeam\Intercall\Contracts\Bridge\Logger;
use DeployTeam\Intercall\Enums\LogLevel;
use DeployTeam\Intercall\Services\RequestListener;
use DeployTeam\IntercallLaravel\Bridge\LaravelConsoleOutput;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

class ListenCommand extends Command
{
    protected $signature = 'intercall:listen
                            {--transport= : Transport ID to listen on}
                            {--workers=1 : Number of worker processes}
                            {--watch : Watch for file changes and auto-restart}';

    protected $description = 'Listen for incoming inter-system requests';

    public function handle(RequestListener $listener, SystemRegistry $systemRegistry, Logger $logger): int
    {
        $this->configureLogLevel($logger);

        $output = new LaravelConsoleOutput($this);

        $basePath = base_path();
        $watchPaths = config('intercall.watch.paths', []);
        $watchIgnorePatterns = config('intercall.watch.ignore', []);
        $watchPollInterval = config('intercall.watch.poll_interval', 1);
        $watchRestartDelay = config('intercall.watch.restart_delay', 1);
        $restartCommand = $this->buildRestartCommand();

        $transport = $this->option('transport');
        $pidFilePath = $transport !== null ? storage_path("intercall/{$transport}.pid") : null;
        $shutdownTimeout = (int) config('intercall.shutdown_timeout', 5);

        $command = new CoreListenCommand(
            $listener,
            $systemRegistry,
            $output,
            $basePath,
            $watchPaths,
            $watchIgnorePatterns,
            $watchPollInterval,
            $watchRestartDelay,
            $restartCommand,
            $pidFilePath,
            $shutdownTimeout,
        );

        return $command->execute();
    }

    /** @return array<int, string> */
    protected function buildRestartCommand(): array
    {
        $parts = [
            PHP_BINARY,
            base_path('artisan'),
            'intercall:listen',
        ];

        $transport = $this->option('transport');
        if ($transport !== null) {
            $parts[] = '--transport=' . $transport;
        }

        $workers = $this->option('workers');
        if ($workers !== null && $workers !== '1') {
            $parts[] = '--workers=' . $workers;
        }

        return $parts;
    }

    protected function configureLogLevel(Logger $logger): void
    {
        $verbosity = $this->getOutput()->getVerbosity();

        if ($verbosity <= OutputInterface::VERBOSITY_NORMAL) {
            return;
        }

        $logger->setMinimumLevel(LogLevel::fromVerbosity($verbosity));
    }
}
