<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Bridge;

use DeployTeam\Intercall\Contracts\Bridge\Container;
use Illuminate\Contracts\Container\Container as LaravelContainerInterface;

class LaravelContainer implements Container
{
    public function __construct(protected LaravelContainerInterface $laravelContainer) {}

    /**
     * @template T of object
     * @param class-string<T> $abstract
     * @return T
     */
    public function make(string $abstract): object
    {
        /** @var T */
        return $this->laravelContainer->make($abstract);
    }
}
