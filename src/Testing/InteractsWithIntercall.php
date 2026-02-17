<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Testing;

use DeployTeam\Intercall\Contracts\IntercallHubContract;
use DeployTeam\Intercall\Services\IntercallHub;

trait InteractsWithIntercall
{
    protected ?FakeIntercallHub $fakeIntercallHub = null;

    protected function fakeIntercall(): FakeIntercallHub
    {
        $this->fakeIntercallHub = new FakeIntercallHub();

        $this->app->instance(IntercallHub::class, $this->fakeIntercallHub);
        $this->app->instance(IntercallHubContract::class, $this->fakeIntercallHub);

        return $this->fakeIntercallHub;
    }
}
