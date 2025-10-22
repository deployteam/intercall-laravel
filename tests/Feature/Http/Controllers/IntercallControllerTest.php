<?php

declare(strict_types=1);

namespace DeployTeam\IntercallLaravel\Tests\Feature\Http\Controllers;

use DeployTeam\IntercallLaravel\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

final class IntercallControllerTest extends TestCase
{
    #[Test]
    public function returnsErrorWhenMissingSourceHeader(): void
    {
        $response = $this->postJson('/api/intercall', [
            'data' => 'test',
        ], [
            'X-Intercall-Event' => 'TestEvent',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonFragment([
                'error' => 'Missing required headers: X-Intercall-Source, X-Intercall-Event',
            ]);
    }

    #[Test]
    public function returnsErrorWhenMissingEventHeader(): void
    {
        $response = $this->postJson('/api/intercall', [
            'data' => 'test',
        ], [
            'X-Intercall-Source' => 'system1',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonFragment([
                'error' => 'Missing required headers: X-Intercall-Source, X-Intercall-Event',
            ]);
    }

    #[Test]
    public function returnsErrorWhenBothHeadersMissing(): void
    {
        $response = $this->postJson('/api/intercall', [
            'data' => 'test',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'Missing required headers: X-Intercall-Source, X-Intercall-Event',
            ]);
    }

    #[Test]
    public function returnsErrorWhenEventNotRegistered(): void
    {
        $response = $this->postJson('/api/intercall', [], [
            'X-Intercall-Source' => 'system1',
            'X-Intercall-Event' => 'UnknownEvent',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'No handler registered for event: UnknownEvent',
            ]);
    }

    #[Test]
    public function registersRoute(): void
    {
        $result = Route::has('intercall.handle');

        static::assertTrue($result);
    }
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        config()->set('intercall.auth.enabled', false);
        config()->set('intercall.rate_limiting.enabled', false);
        config()->set('intercall.http_fallback.enabled', true);
        config()->set('intercall.http_fallback.endpoint', '/api/intercall');
    }
}
