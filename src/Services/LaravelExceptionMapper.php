<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Services;

use DeployTeam\Intercall\Contracts\IntercallErrorResponse;
use DeployTeam\Intercall\Contracts\IntercallExceptionMapper;
use DeployTeam\Intercall\Services\ConventionExceptionMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class LaravelExceptionMapper implements IntercallExceptionMapper
{
    public function __construct(private readonly ConventionExceptionMapper $convention) {}

    public function map(Throwable $exception): IntercallErrorResponse
    {
        if (method_exists($exception, 'toIntercallError')) {
            return $this->convention->map($exception);
        }

        if (method_exists($exception, 'render')) {
            $response = $exception->render(Request::createFromGlobals());

            if ($response instanceof JsonResponse) {
                $data = $response->getData(true);

                if (is_array($data)) {
                    $errors = $data['errors'] ?? null;
                    $context = $data['context'] ?? null;

                    return new IntercallErrorResponse(
                        (string) ($data['code'] ?? 'error.unhandled'),
                        (string) ($data['message'] ?? $exception->getMessage()),
                        is_array($errors) ? $errors : (is_array($context) ? $context : []),
                    );
                }
            }
        }

        return $this->convention->map($exception);
    }
}
