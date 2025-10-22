<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Bridge;

use DeployTeam\Intercall\Contracts\Bridge\EventDispatcher;

class LaravelEventDispatcher implements EventDispatcher
{
    public function dispatch(object $event): void
    {
        event($event);
    }
}
