<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Bridge;

use DeployTeam\Intercall\Contracts\Bridge\JobDispatcher;

class LaravelJobDispatcher implements JobDispatcher
{
    public function dispatch(callable $job): void
    {
        dispatch($job);
    }
}
