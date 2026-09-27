<?php

namespace Tests\Feature;

use App\Models\Website;
use App\Services\WebsiteInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckWebsitesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_checks_every_website_and_saves_the_latest_status(): void
    {
        $first = Website::factory()->create([
            'domain' => 'alpha.example.com',
            'status_code' => 200,
            'wordpress_version' => '6.8.3',
            'ssl_expires_at' => '2027-01-10 00:00:00',
            'checked_at' => '2026-09-25 10:00:00',
        ]);
        $second = Website::factory()->create(['domain' => 'beta.example.com']);
        $this->travelTo('2026-09-27 10:00:00');

        $inspector = $this->mock(WebsiteInspector::class);
        $inspector->shouldReceive('inspect')->twice()->andReturnUsing(fn (Website $website): array => [
            'status_code' => 200,
            'wordpress_version' => $website->id === $first->id ? '7.1.2' : '6.8.3',
            'ssl_expires_at' => new \DateTimeImmutable('2027-01-10'),
            'checked_at' => now(),
            'check_error' => null,
        ]);

        $this->artisan('websites:check')
            ->expectsOutput('Checked 2 websites: 2 succeeded, 0 failed.')
            ->assertSuccessful();

        $this->assertSame(200, $first->fresh()->status_code);
        $this->assertSame('7.1.2', $first->fresh()->wordpress_version);
        $this->assertSame('2027-01-10', $first->fresh()->ssl_expires_at?->format('Y-m-d'));
        $this->assertSame('2026-09-27 10:00:00', $first->fresh()->checked_at?->toDateTimeString());
        $this->assertSame('6.8.3', $second->fresh()->wordpress_version);
    }

    public function test_command_reports_failures_and_continues_checking_remaining_websites(): void
    {
        $first = Website::factory()->create([
            'domain' => 'alpha.example.com',
            'status_code' => 200,
            'wordpress_version' => '6.8.3',
            'ssl_expires_at' => '2027-01-10 00:00:00',
            'checked_at' => '2026-09-25 10:00:00',
        ]);
        $second = Website::factory()->create(['domain' => 'beta.example.com']);

        $inspector = $this->mock(WebsiteInspector::class);
        $inspector->shouldReceive('inspect')->twice()->andReturnUsing(fn (Website $website): array => [
            'status_code' => $website->id === $first->id ? 401 : 200,
            'wordpress_version' => $website->id === $first->id ? null : '7.1.2',
            'ssl_expires_at' => null,
            'checked_at' => now(),
            'check_error' => $website->id === $first->id ? 'WordPress credentials could not be verified.' : null,
        ]);

        $this->artisan('websites:check')
            ->expectsOutput('Checked 2 websites: 1 succeeded, 1 failed.')
            ->assertExitCode(1);

        $this->assertSame(401, $first->fresh()->status_code);
        $this->assertSame('6.8.3', $first->fresh()->wordpress_version);
        $this->assertSame('2027-01-10', $first->fresh()->ssl_expires_at?->format('Y-m-d'));
        $this->assertSame('2026-09-25 10:00:00', $first->fresh()->checked_at?->toDateTimeString());
        $this->assertNull($first->fresh()->check_error);
        $this->assertSame(200, $second->fresh()->status_code);
    }
}
