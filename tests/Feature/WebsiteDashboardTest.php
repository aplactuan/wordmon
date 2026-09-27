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

    public function test_a_user_cannot_open_another_users_credentials_form(): void
    {
        $website = Website::factory()->create();

        Livewire::actingAs(User::factory()->create())->test(Dashboard::class)
            ->call('openEdit', $website->id)
            ->assertNotFound();
    }

    public function test_a_user_cannot_update_another_users_credentials_by_changing_the_editing_id(): void
    {
        $user = User::factory()->create();
        $ownWebsite = Website::factory()->for($user)->create();
        $otherWebsite = Website::factory()->create(['username' => 'original', 'application_password' => 'original password']);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->call('openEdit', $ownWebsite->id)
            ->set('editingWebsiteId', $otherWebsite->id)
            ->set('editingUsername', 'changed')
            ->set('editingApplicationPassword', 'changed password')
            ->call('updateCredentials')
            ->assertNotFound();

        $this->assertSame('original', $otherWebsite->fresh()->username);
        $this->assertSame('original password', $otherWebsite->fresh()->application_password);
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

    public function test_summary_counts_each_website_once_when_http_is_200_but_ssl_expires_soon(): void
    {
        $this->travelTo('2026-09-27 10:00:00');
        $user = User::factory()->create();
        Website::factory()->for($user)->create(['status_code' => 200, 'ssl_expires_at' => '2027-01-10 00:00:00']);
        Website::factory()->for($user)->create(['status_code' => 200, 'ssl_expires_at' => '2026-10-10 00:00:00']);
        Website::factory()->for($user)->create(['status_code' => 503]);
        Website::factory()->for($user)->create(['status_code' => null]);
        Website::factory()->create(['status_code' => 200]);

        $totals = Livewire::actingAs($user)->test(Dashboard::class)->viewData('totals');

        $this->assertSame(4, (int) $totals->total);
        $this->assertSame(1, (int) $totals->healthy);
        $this->assertSame(3, (int) $totals->attention);
    }

    public function test_needs_attention_filter_matches_the_summary_and_combines_with_domain_search(): void
    {
        $this->travelTo('2026-09-27 10:00:00');
        $user = User::factory()->create();
        Website::factory()->for($user)->create(['domain' => 'healthy.example.com', 'status_code' => 200, 'ssl_expires_at' => '2027-01-10']);
        Website::factory()->for($user)->create(['domain' => 'expiring.example.com', 'status_code' => 200, 'ssl_expires_at' => '2026-10-10']);
        Website::factory()->for($user)->create(['domain' => 'failed.example.com', 'status_code' => 503]);
        Website::factory()->for($user)->create(['domain' => 'unchecked.example.com']);
        Website::factory()->for($user)->create(['domain' => 'error.example.com', 'status_code' => 200, 'check_error' => 'Certificate could not be verified.']);
        Website::factory()->create(['domain' => 'someone-else.example.com', 'status_code' => 503]);

        $component = Livewire::actingAs($user)->test(Dashboard::class)
            ->call('filterByAttention', true)
            ->assertSet('attentionOnly', true);

        $this->assertSame(4, (int) $component->viewData('totals')->attention);
        $this->assertSame(['error.example.com', 'expiring.example.com', 'failed.example.com', 'unchecked.example.com'], $component->viewData('websites')->getCollection()->pluck('domain')->all());

        $component->set('search', 'expiring');

        $this->assertSame(['expiring.example.com'], $component->viewData('websites')->getCollection()->pluck('domain')->all());
    }

    public function test_ssl_expiry_sort_toggles_direction_and_keeps_missing_dates_last(): void
    {
        $user = User::factory()->create();
        Website::factory()->for($user)->create(['domain' => 'alpha.example.com']);
        Website::factory()->for($user)->create(['domain' => 'beta.example.com', 'ssl_expires_at' => '2027-03-01']);
        Website::factory()->for($user)->create(['domain' => 'gamma.example.com', 'ssl_expires_at' => '2027-01-01']);
        Website::factory()->for($user)->create(['domain' => 'delta.example.com']);

        $component = Livewire::actingAs($user)->test(Dashboard::class)
            ->call('sortBySslExpiry')
            ->assertSet('sslSortDirection', 'asc');

        $this->assertSame(['gamma.example.com', 'beta.example.com', 'alpha.example.com', 'delta.example.com'], $component->viewData('websites')->getCollection()->pluck('domain')->all());

        $component->call('sortBySslExpiry')->assertSet('sslSortDirection', 'desc');

        $this->assertSame(['beta.example.com', 'gamma.example.com', 'alpha.example.com', 'delta.example.com'], $component->viewData('websites')->getCollection()->pluck('domain')->all());
    }

    public function test_switching_to_needs_attention_resets_pagination(): void
    {
        $user = User::factory()->create();
        Website::factory()->for($user)->count(11)->create(['status_code' => 200]);
        Website::factory()->for($user)->create(['domain' => 'needs-attention.example.com', 'status_code' => 503]);

        $component = Livewire::actingAs($user)->test(Dashboard::class)
            ->call('gotoPage', 2)
            ->call('filterByAttention', true);

        $this->assertSame(1, $component->viewData('websites')->currentPage());
        $this->assertSame(['needs-attention.example.com'], $component->viewData('websites')->getCollection()->pluck('domain')->all());
    }

    public function test_the_edit_form_does_not_expose_the_saved_application_password(): void
    {
        $website = Website::factory()->create(['username' => 'site-admin', 'application_password' => 'private password']);

        Livewire::actingAs($website->user)->test(Dashboard::class)
            ->assertSee('Edit credentials')
            ->assertDontSee('n8n setup')
            ->call('openEdit', $website->id)
            ->assertSet('showEditForm', true)
            ->assertSet('editingDomain', $website->domain)
            ->assertSet('editingUsername', 'site-admin')
            ->assertSet('editingApplicationPassword', '')
            ->assertDontSee('private password');
    }

    public function test_a_user_can_update_a_websites_username_and_application_password(): void
    {
        $website = Website::factory()->create([
            'username' => 'old-admin',
            'application_password' => 'old password',
            'status_code' => 200,
        ]);

        Livewire::actingAs($website->user)->test(Dashboard::class)
            ->call('openEdit', $website->id)
            ->set('editingUsername', 'new-admin')
            ->set('editingApplicationPassword', 'new password')
            ->call('updateCredentials')
            ->assertHasNoErrors()
            ->assertSet('showEditForm', false)
            ->assertSee('new-admin');

        $updated = $website->fresh();
        $this->assertSame('new-admin', $updated->username);
        $this->assertSame('new password', $updated->application_password);
        $this->assertStringNotContainsString('new password', $updated->getRawOriginal('application_password'));
        $this->assertSame(200, $updated->status_code);
    }

    public function test_leaving_the_new_password_blank_keeps_the_current_password(): void
    {
        $website = Website::factory()->create(['username' => 'old-admin', 'application_password' => 'current password']);
        $encryptedPassword = $website->getRawOriginal('application_password');

        Livewire::actingAs($website->user)->test(Dashboard::class)
            ->call('openEdit', $website->id)
            ->set('editingUsername', 'new-admin')
            ->call('updateCredentials')
            ->assertHasNoErrors();

        $this->assertSame('new-admin', $website->fresh()->username);
        $this->assertSame('current password', $website->fresh()->application_password);
        $this->assertSame($encryptedPassword, $website->fresh()->getRawOriginal('application_password'));
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
        $website = Website::factory()->create([
            'status_code' => 200,
            'wordpress_version' => '6.8.3',
            'ssl_expires_at' => '2027-01-10 00:00:00',
            'checked_at' => '2026-09-25 10:00:00',
        ]);

        $inspector = $this->mock(WebsiteInspector::class);
        $inspector->shouldReceive('inspect')->once()->andReturn([
            'status_code' => 503,
            'wordpress_version' => null,
            'ssl_expires_at' => null,
            'checked_at' => now(),
            'check_error' => 'The site could not be checked.',
        ]);

        Livewire::actingAs($website->user)->test(Dashboard::class)
            ->call('checkWebsite', $website->id)
            ->assertSee('The site could not be checked.');

        $this->assertSame(503, $website->fresh()->status_code);
        $this->assertSame('6.8.3', $website->fresh()->wordpress_version);
        $this->assertSame('2027-01-10', $website->fresh()->ssl_expires_at?->format('Y-m-d'));
        $this->assertSame('2026-09-25 10:00:00', $website->fresh()->checked_at?->toDateTimeString());
        $this->assertNull($website->fresh()->check_error);
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
        $csv = UploadedFile::fake()->createWithContent('websites.csv', "domain,username,application_password\nhttps://EXISTING.example.com/,admin,new password\nshared.example.com,editor,shared password\nHTTPS://SHARED.EXAMPLE.COM/,editor,duplicate password\n");

        Livewire::actingAs($user)->test(Dashboard::class)
            ->set('csvFile', $csv)
            ->call('importCsv')
            ->assertHasNoErrors()
            ->assertSee('1 imported')
            ->assertSee('2 duplicates skipped');

        $this->assertSame('original password', Website::query()->where('user_id', $user->id)->where('domain', 'existing.example.com')->sole()->application_password);
        $this->assertSame('shared password', Website::query()->where('user_id', $user->id)->where('domain', 'shared.example.com')->sole()->application_password);
        $this->assertSame(2, Website::query()->where('user_id', $user->id)->count());
    }

    public function test_importing_the_same_csv_again_skips_every_domain_without_changing_credentials(): void
    {
        $user = User::factory()->create();
        $firstCsv = UploadedFile::fake()->createWithContent('websites.csv', "domain,username,application_password\nalpha.example.com,admin,original password\nbeta.example.com,editor,second password\n");

        Livewire::actingAs($user)->test(Dashboard::class)
            ->set('csvFile', $firstCsv)
            ->call('importCsv')
            ->assertHasNoErrors()
            ->assertSee('2 imported');

        $secondCsv = UploadedFile::fake()->createWithContent('websites.csv', "domain,username,application_password\nhttps://ALPHA.example.com/,other,replacement password\nBETA.EXAMPLE.COM,other,replacement password\n");

        Livewire::actingAs($user)->test(Dashboard::class)
            ->set('csvFile', $secondCsv)
            ->call('importCsv')
            ->assertHasNoErrors()
            ->assertSee('0 imported')
            ->assertSee('2 duplicates skipped');

        $this->assertSame(2, Website::query()->where('user_id', $user->id)->count());
        $this->assertSame('original password', Website::query()->where('user_id', $user->id)->where('domain', 'alpha.example.com')->sole()->application_password);
        $this->assertSame('second password', Website::query()->where('user_id', $user->id)->where('domain', 'beta.example.com')->sole()->application_password);
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
