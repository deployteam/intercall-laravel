<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Tests\Feature\Http\Middleware;

use DeployTeam\IntercallLaravel\Http\Middleware\RestrictToAllowedIps;
use DeployTeam\IntercallLaravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(RestrictToAllowedIps::class)]
final class RestrictToAllowedIpsTest extends TestCase
{
    #[Test]
    public function allowsAnyIpWhenAllowedListIsEmpty(): void
    {
        config()->set('intercall.http_fallback.allowed_ips', []);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
            ->postJson('/api/intercall', ['data' => 'test']);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'error' => 'Missing required headers: X-Intercall-Source, X-Intercall-Event',
            ]);
    }

    #[Test]
    public function allowsIpInsideAllowedRange(): void
    {
        config()->set('intercall.http_fallback.allowed_ips', ['10.0.0.0/8']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.3.10.72'])
            ->postJson('/api/intercall', ['data' => 'test']);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'error' => 'Missing required headers: X-Intercall-Source, X-Intercall-Event',
            ]);
    }

    #[Test]
    public function rejectsIpOutsideAllowedRange(): void
    {
        config()->set('intercall.http_fallback.allowed_ips', ['10.0.0.0/8']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
            ->postJson('/api/intercall', ['data' => 'test'], [
                'X-Intercall-Source' => 'operation',
                'X-Intercall-Event' => 'TestEvent',
            ]);

        $response->assertStatus(403)
            ->assertExactJson([
                'success' => false,
                'error' => 'Forbidden',
            ]);
    }

    #[Test]
    public function rejectsCallbackMessageFromIpOutsideAllowedRange(): void
    {
        config()->set('intercall.http_fallback.allowed_ips', ['10.0.0.0/8']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
            ->postJson('/api/intercall', [
                'message_type' => 'callback',
                'request_id' => 'request-1',
                'original_event_name' => 'TestEvent',
                'result' => [],
                'success' => true,
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function rejectsHeartbeatFromIpOutsideAllowedRange(): void
    {
        config()->set('intercall.http_fallback.allowed_ips', ['10.0.0.0/8']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
            ->getJson('/api/intercall/heartbeat');

        $response->assertStatus(403);
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        config()->set('intercall.auth.enabled', false);
        config()->set('intercall.http_fallback.enabled', true);
        config()->set('intercall.http_fallback.endpoint', '/api/intercall');
    }
}
