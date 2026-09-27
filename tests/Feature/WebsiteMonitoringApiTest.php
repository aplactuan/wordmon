<?php

namespace Tests\Feature;

use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteMonitoringApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_n8n_cannot_retrieve_the_stored_wordpress_credentials(): void
    {
        $website = Website::factory()->create();
        $token = $website->ensureWebhookToken();

        $this->withToken($token)
            ->getJson('/api/monitoring/websites/'.$website->id)
            ->assertNotFound();
    }

    public function test_missing_or_invalid_token_cannot_read_or_update_a_website(): void
    {
        $website = Website::factory()->create();
        $website->ensureWebhookToken();
        $payload = ['domain' => $website->domain, 'status_code' => 200, 'checked_at' => now()->toIso8601String()];

        $this->postJson(route('api.monitoring.websites.checks.store', $website), $payload)->assertNotFound();
        $this->withToken('wrong-token')
            ->postJson(route('api.monitoring.websites.checks.store', $website), $payload)
            ->assertNotFound();

        $this->assertNull($website->fresh()->checked_at);
    }

    public function test_n8n_can_submit_the_latest_result_for_a_domain(): void
    {
        $website = Website::factory()->create(['domain' => 'alpha.example.com']);

        $this->withToken($website->ensureWebhookToken())
            ->postJson(route('api.monitoring.websites.checks.store', $website), [
                'domain' => 'alpha.example.com',
                'status_code' => 200,
                'wordpress_version' => '6.6.2',
                'ssl_expires_at' => '2027-01-10T00:00:00Z',
                'checked_at' => '2026-09-25T10:30:00Z',
                'check_error' => null,
            ])
            ->assertOk()
            ->assertExactJson(['updated' => true]);

        $website->refresh();
        $this->assertSame(200, $website->status_code);
        $this->assertSame('6.6.2', $website->wordpress_version);
        $this->assertSame('2027-01-10', $website->ssl_expires_at?->format('Y-m-d'));
        $this->assertSame('2026-09-25 10:30:00', $website->checked_at?->format('Y-m-d H:i:s'));
    }

    public function test_a_failed_check_can_be_reported_without_an_http_status(): void
    {
        $website = Website::factory()->create([
            'status_code' => 200,
            'wordpress_version' => '6.8.3',
            'ssl_expires_at' => '2027-01-10 00:00:00',
            'checked_at' => '2026-09-25 10:00:00',
        ]);

        $this->withToken($website->ensureWebhookToken())
            ->postJson(route('api.monitoring.websites.checks.store', $website), [
                'domain' => $website->domain,
                'status_code' => null,
                'checked_at' => now()->toIso8601String(),
                'check_error' => 'Connection timed out',
            ])
            ->assertOk();

        $this->assertNull($website->fresh()->status_code);
        $this->assertSame('6.8.3', $website->fresh()->wordpress_version);
        $this->assertSame('2027-01-10', $website->fresh()->ssl_expires_at?->format('Y-m-d'));
        $this->assertSame('2026-09-25 10:00:00', $website->fresh()->checked_at?->toDateTimeString());
        $this->assertNull($website->fresh()->check_error);
    }

    public function test_a_non_200_webhook_result_only_replaces_the_status_code(): void
    {
        $website = Website::factory()->create([
            'status_code' => 200,
            'wordpress_version' => '6.8.3',
            'ssl_expires_at' => '2027-01-10 00:00:00',
            'checked_at' => '2026-09-25 10:00:00',
        ]);

        $this->withToken($website->ensureWebhookToken())
            ->postJson(route('api.monitoring.websites.checks.store', $website), [
                'domain' => $website->domain,
                'status_code' => 503,
                'wordpress_version' => null,
                'ssl_expires_at' => null,
                'checked_at' => '2026-09-27T10:00:00Z',
                'check_error' => 'Service unavailable',
            ])
            ->assertOk()
            ->assertExactJson(['updated' => true]);

        $website->refresh();
        $this->assertSame(503, $website->status_code);
        $this->assertSame('6.8.3', $website->wordpress_version);
        $this->assertSame('2027-01-10', $website->ssl_expires_at?->format('Y-m-d'));
        $this->assertSame('2026-09-25 10:00:00', $website->checked_at?->toDateTimeString());
        $this->assertNull($website->check_error);
    }

    public function test_mismatched_domain_and_invalid_status_are_rejected(): void
    {
        $website = Website::factory()->create(['domain' => 'alpha.example.com']);
        $this->withToken($website->ensureWebhookToken())
            ->postJson(route('api.monitoring.websites.checks.store', $website), [
                'domain' => 'beta.example.com',
                'status_code' => 700,
                'checked_at' => now()->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain', 'status_code']);

        $this->assertNull($website->fresh()->checked_at);
    }

    public function test_an_older_result_does_not_replace_a_newer_one(): void
    {
        $website = Website::factory()->create([
            'status_code' => 200,
            'checked_at' => '2026-09-25 11:30:00',
        ]);

        $this->withToken($website->ensureWebhookToken())
            ->postJson(route('api.monitoring.websites.checks.store', $website), [
                'domain' => $website->domain,
                'status_code' => 503,
                'checked_at' => '2026-09-25T10:30:00Z',
            ])
            ->assertOk()
            ->assertExactJson(['updated' => false]);

        $this->assertSame(200, $website->fresh()->status_code);
    }

    public function test_a_result_too_far_in_the_future_is_rejected(): void
    {
        $website = Website::factory()->create();

        $this->withToken($website->ensureWebhookToken())
            ->postJson(route('api.monitoring.websites.checks.store', $website), [
                'domain' => $website->domain,
                'status_code' => 200,
                'checked_at' => now()->addDay()->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('checked_at');

        $this->assertNull($website->fresh()->checked_at);
    }
}
