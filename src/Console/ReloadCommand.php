<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Console;

use Illuminate\Console\Command;

class ReloadCommand extends Command
{
    /** @var string */
    protected $signature = 'intercall:reload
                            {--transport= : Transport ID to reload (reloads all if not specified)}';

    /** @var string */
    protected $description = 'Reload intercall listener workers';

    public function handle(): int
    {
        $transport = $this->option('transport');

        if ($transport !== null) {
            return $this->reloadTransport((string) $transport);
        }

        $pidDirectory = storage_path('intercall');
        $pidFiles = glob("{$pidDirectory}/*.pid");

        if ($pidFiles === [] || $pidFiles === false) {
            $this->warn('No running intercall listeners found.');

            return Command::FAILURE;
        }

        $failed = false;

        foreach ($pidFiles as $pidFile) {
            $transportId = basename($pidFile, '.pid');

            if ($this->reloadTransport($transportId) !== Command::SUCCESS) {
                $failed = true;
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    private function reloadTransport(string $transport): int
    {
        $pidFile = storage_path("intercall/{$transport}.pid");

        if (!file_exists($pidFile)) {
            $this->error("No PID file found for transport '{$transport}'.");

            return Command::FAILURE;
        }

        $pid = (int) file_get_contents($pidFile);

        if ($pid <= 0 || !posix_kill($pid, 0)) {
            $this->error("Process {$pid} for transport '{$transport}' is not running.");
            @unlink($pidFile);

            return Command::FAILURE;
        }

        posix_kill($pid, SIGUSR1);
        $this->info("Reload signal sent to transport '{$transport}' (PID: {$pid}).");

        return Command::SUCCESS;
    }
}
