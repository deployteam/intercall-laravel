<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Bridge;

use DeployTeam\Intercall\Contracts\Bridge\HttpClient;
use DeployTeam\Intercall\Contracts\Bridge\HttpResponse;
use Illuminate\Support\Facades\Http;

class LaravelHttpClient implements HttpClient
{
    public function request(string $method, string $url, array $options = []): HttpResponse
    {
        $request = Http::timeout($options['timeout'] ?? 30);

        if (!empty($options['insecure'])) {
            $request = $request->withoutVerifying();
        }

        if (isset($options['headers'])) {
            $request = $request->withHeaders($options['headers']);
        }

        $data = $options['body'] ?? [];

        if (isset($options['json'])) {
            $data = ['json' => $options['json']];
        }

        $response = $request->send($method, $url, $data);

        return new LaravelHttpResponse($response);
    }
}
