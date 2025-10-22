<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Bridge;

use DeployTeam\Intercall\Contracts\Bridge\HttpResponse;
use Illuminate\Http\Client\Response;

class LaravelHttpResponse implements HttpResponse
{
    public function __construct(protected Response $response) {}

    public function getStatusCode(): int
    {
        return $this->response->status();
    }

    public function getBody(): string
    {
        return $this->response->body();
    }

    public function toArray(): array
    {
        return $this->response->json() ?? [];
    }

    public function isSuccessful(): bool
    {
        return $this->response->successful();
    }
}
