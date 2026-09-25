<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\User;
use App\Models\Website;
use App\Services\WebsiteInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class WebsiteDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_add_a_website_and_its_password_is_encrypted(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Dashboard::class)
            ->set('domain', 'https://Example.com/')
            ->set('username', 'site-admin')
            ->set('applicationPassword', 'abcd efgh ijkl mnop')
            ->call('addWebsite')
            ->assertHasNoErrors()
            ->assertSee('example.com');

        $website = Website::query()->sole();
        $this->assertSame($user->id, $website->user_id);
        $this->assertSame('example.com', $website->domain);
        $this->assertSame('abcd efgh ijkl mnop', $website->application_password);
        $this->assertStringNotContainsString('abcd efgh', $website->getRawOriginal('application_password'));
        $this->assertNotNull($website->webhook_token);
        $this->assertStringNotContainsString($website->webhook_token, $website->getRawOriginal('webhook_token'));
    }

    public function test_domain_search_only_shows_the_users_matching_websites(): void
    {
        $user = User::factory()->create();
        Website::factory()->for($user)->create(['domain' => 'alpha.example.com']);
        Website::factory()->for($user)->create(['domain' => 'beta.example.com']);
        Website::factory()->create(['domain' => 'private.example.com']);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->set('search', 'alpha')
            ->assertSee('alpha.example.com')
            ->assertDontSee('beta.example.com')
            ->assertDontSee('private.example.com');
    }

    public function test_a_user_cannot_view_another_users_integration_token(): void
    {
        $website = Website::factory()->create();

        Livewire::actingAs(User::factory()->create())->test(Dashboard::class)
            ->call('showIntegration', $website->id)
            ->assertNotFound();
    }

    public function test_a_user_cannot_manually_check_another_users_website(): void
    {
        $website = Website::factory()->create();

        Livewire::actingAs(User::factory()->create())->test(Dashboard::class)
            ->call('checkWebsite', $website->id)
            ->assertNotFound();
    }

    public function test_duplicate_domains_are_rejected_for_the_same_user(): void
    {
        $user = User::factory()->create();
        Website::factory()->for($user)->create(['domain' => 'example.com']);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->set('domain', 'example.com')
            ->set('username', 'admin')
            ->set('applicationPassword', 'secret')
            ->call('addWebsite')
            ->assertHasErrors(['domain' => 'unique']);

        $this->assertSame(1, Website::query()->where('user_id', $user->id)->count());
    }

    public function test_latest_monitoring_details_are_shown(): void
    {
        $user = User::factory()->create();
        Website::factory()->for($user)->create([
            'domain' => 'details.example.com',
            'status_code' => 200,
            'wordpress_version' => '6.6.2',
            'ssl_expires_at' => '2027-01-10 00:00:00',
            'checked_at' => '2026-09-25 10:30:00',
        ]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('details.example.com')
            ->assertSee('HTTP 200')
            ->assertSee('v6.6.2')
            ->assertSee('Jan 10, 2027')
            ->assertSee('Sep 25, 2026');
    }

    public function test_an_existing_website_gets_a_token_when_its_integration_is_opened(): void
    {
        $website = Website::factory()->create();

        Livewire::actingAs($website->user)->test(Dashboard::class)
            ->call('showIntegration', $website->id)
            ->assertSet('showIntegration', true)
            ->assertSee(route('api.monitoring.websites.checks.store', $website));

        $this->assertNotNull($website->fresh()->webhook_token);
    }

    public function test_check_now_uses_the_saved_credentials_and_updates_the_latest_result(): void
    {
        $website = Website::factory()->create();

        $inspector = $this->mock(WebsiteInspector::class);
        $inspector->shouldReceive('inspect')->once()->withArgs(fn (Website $inspectedWebsite): bool => $inspectedWebsite->id === $website->id
            && $inspectedWebsite->application_password === $website->application_password)->andReturn([
                'status_code' => 200,
                'wordpress_version' => '6.6.2',
                'ssl_expires_at' => new \DateTimeImmutable('2027-01-10'),
                'checked_at' => now(),
                'check_error' => null,
            ]);

        Livewire::actingAs($website->user)->test(Dashboard::class)
            ->call('checkWebsite', $website->id)
            ->assertHasNoErrors()
            ->assertSee('HTTP 200');

        $this->assertSame(200, $website->fresh()->status_code);
        $this->assertSame('6.6.2', $website->fresh()->wordpress_version);
    }

    public function test_check_now_records_a_failed_check(): void
    {
        $website = Website::factory()->create();

        $inspector = $this->mock(WebsiteInspector::class);
        $inspector->shouldReceive('inspect')->once()->andReturn([
            'status_code' => null,
            'wordpress_version' => null,
            'ssl_expires_at' => null,
            'checked_at' => now(),
            'check_error' => 'The site could not be checked.',
        ]);

        Livewire::actingAs($website->user)->test(Dashboard::class)
            ->call('checkWebsite', $website->id)
            ->assertSee('The site could not be checked.');

        $this->assertSame('The site could not be checked.', $website->fresh()->check_error);
        $this->assertNotNull($website->fresh()->checked_at);
    }

    public function test_a_user_can_import_websites_and_encrypted_credentials_from_csv(): void
    {
        $user = User::factory()->create();
        $csv = UploadedFile::fake()->createWithContent('websites.csv', "domain,username,application_password\nhttps://Alpha.example.com/,admin,first password\nbeta.example.com,editor,\"part,with,comma\"\n");

        Livewire::actingAs($user)->test(Dashboard::class)
            ->set('csvFile', $csv)
            ->call('importCsv')
            ->assertHasNoErrors()
            ->assertSee('2 imported');

        $alpha = Website::query()->where('user_id', $user->id)->where('domain', 'alpha.example.com')->sole();
        $beta = Website::query()->where('user_id', $user->id)->where('domain', 'beta.example.com')->sole();
        $this->assertSame('first password', $alpha->application_password);
        $this->assertSame('part,with,comma', $beta->application_password);
        $this->assertStringNotContainsString('first password', $alpha->getRawOriginal('application_password'));
        $this->assertNotNull($alpha->webhook_token);
    }

    public function test_csv_import_skips_existing_domains_without_replacing_credentials(): void
    {
        $user = User::factory()->create();
        Website::factory()->for($user)->create(['domain' => 'existing.example.com', 'application_password' => 'original password']);
        Website::factory()->create(['domain' => 'shared.example.com']);
        $csv = UploadedFile::fake()->createWithContent('websites.csv', "domain,username,application_password\nexisting.example.com,admin,new password\nshared.example.com,editor,shared password\nshared.example.com,editor,duplicate password\n");

        Livewire::actingAs($user)->test(Dashboard::class)
            ->set('csvFile', $csv)
            ->call('importCsv')
            ->assertHasNoErrors()
            ->assertSee('1 imported')
            ->assertSee('2 duplicates skipped');

        $this->assertSame('original password', Website::query()->where('user_id', $user->id)->where('domain', 'existing.example.com')->sole()->application_password);
        $this->assertSame('shared password', Website::query()->where('user_id', $user->id)->where('domain', 'shared.example.com')->sole()->application_password);
    }

    public function test_csv_import_reports_invalid_rows_without_exposing_passwords(): void
    {
        $user = User::factory()->create();
        $csv = UploadedFile::fake()->createWithContent('websites.csv', "domain,username,application_password\nnot-a-domain,admin,private secret\nvalid.example.com,editor,valid password\n");

        Livewire::actingAs($user)->test(Dashboard::class)
            ->set('csvFile', $csv)
            ->call('importCsv')
            ->assertHasNoErrors()
            ->assertSee('1 imported')
            ->assertSee('1 invalid rows')
            ->assertSee('Row 2: invalid domain.')
            ->assertDontSee('private secret');

        $this->assertSame(1, Website::query()->where('user_id', $user->id)->count());
    }

    public function test_csv_import_rejects_an_incorrect_header(): void
    {
        $user = User::factory()->create();
        $csv = UploadedFile::fake()->createWithContent('websites.csv', "url,user,password\nexample.com,admin,secret\n");

        Livewire::actingAs($user)->test(Dashboard::class)
            ->set('csvFile', $csv)
            ->call('importCsv')
            ->assertHasErrors('csvFile');

        $this->assertSame(0, Website::query()->where('user_id', $user->id)->count());
    }

    public function test_csv_import_rejects_more_than_500_rows_without_saving_any(): void
    {
        $user = User::factory()->create();
        $rows = "domain,username,application_password\n";

        for ($number = 1; $number <= 501; $number++) {
            $rows .= "site{$number}.example.com,admin,secret\n";
        }

        $csv = UploadedFile::fake()->createWithContent('websites.csv', $rows);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->set('csvFile', $csv)
            ->call('importCsv')
            ->assertHasErrors('csvFile');

        $this->assertSame(0, Website::query()->where('user_id', $user->id)->count());
    }
}
