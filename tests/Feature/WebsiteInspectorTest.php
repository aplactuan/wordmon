<?php

namespace Tests\Feature;

use App\Models\Website;
use App\Services\WebsiteInspector;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteInspectorTest extends TestCase
{
    public function test_status_endpoint_uses_basic_auth_and_maps_its_wordpress_version(): void
    {
        $this->travelTo('2026-09-27 10:00:00');
        $website = new Website([
            'domain' => 'example.com',
            'username' => 'site-admin',
            'application_password' => 'abcd efgh ijkl mnop',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://example.com/wp-json/wp-autoops/v1/status' => Http::response([
                'success' => true,
                'data' => ['wordpress' => ['version' => '7.1.2']],
            ]),
        ]);

        $result = $this->inspector()->inspect($website);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://example.com/wp-json/wp-autoops/v1/status'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('site-admin:abcd efgh ijkl mnop')));
        Http::assertSentCount(1);
        $this->assertSame(200, $result['status_code']);
        $this->assertSame('7.1.2', $result['wordpress_version']);
        $this->assertSame('2027-01-10', $result['ssl_expires_at']?->format('Y-m-d'));
        $this->assertSame('2026-09-27 10:00:00', $result['checked_at']->toDateTimeString());
        $this->assertNull($result['check_error']);
    }

    public function test_rejected_credentials_record_the_http_status_without_a_wordpress_version(): void
    {
        $website = new Website(['domain' => 'example.com', 'username' => 'admin', 'application_password' => 'wrong']);
        Http::preventStrayRequests();
        Http::fake(['https://example.com/wp-json/wp-autoops/v1/status' => Http::response([], 401)]);

        $result = $this->inspector()->inspect($website);

        Http::assertSentCount(3);
        $this->assertSame(401, $result['status_code']);
        $this->assertNull($result['wordpress_version']);
        $this->assertSame('WordPress credentials could not be verified.', $result['check_error']);
    }

    public function test_invalid_success_payload_records_a_check_error(): void
    {
        $website = new Website(['domain' => 'example.com', 'username' => 'admin', 'application_password' => 'secret']);
        Http::preventStrayRequests();
        Http::fake(['https://example.com/wp-json/wp-autoops/v1/status' => Http::response(['success' => true, 'data' => []])]);

        $result = $this->inspector()->inspect($website);

        $this->assertSame(200, $result['status_code']);
        $this->assertNull($result['wordpress_version']);
        $this->assertSame('Status endpoint returned an invalid response.', $result['check_error']);
    }

    public function test_two_non_200_responses_are_retried_before_a_successful_check(): void
    {
        $website = new Website(['domain' => 'example.com', 'username' => 'admin', 'application_password' => 'secret']);
        Http::preventStrayRequests();
        Http::fake([
            'https://example.com/wp-json/wp-autoops/v1/status' => Http::sequence()
                ->pushStatus(503)
                ->pushStatus(503)
                ->push(['success' => true, 'data' => ['wordpress' => ['version' => '7.1.2']]], 200),
        ]);

        $result = $this->inspector()->inspect($website);

        Http::assertSentCount(3);
        $this->assertSame(200, $result['status_code']);
        $this->assertSame('7.1.2', $result['wordpress_version']);
        $this->assertNull($result['check_error']);
    }

    public function test_three_non_200_responses_return_the_last_status(): void
    {
        $website = new Website(['domain' => 'example.com', 'username' => 'admin', 'application_password' => 'secret']);
        Http::preventStrayRequests();
        Http::fake([
            'https://example.com/wp-json/wp-autoops/v1/status' => Http::sequence()
                ->pushStatus(503)
                ->pushStatus(502)
                ->pushStatus(503),
        ]);

        $result = $this->inspector()->inspect($website);

        Http::assertSentCount(3);
        $this->assertSame(503, $result['status_code']);
        $this->assertSame('Status endpoint returned HTTP 503.', $result['check_error']);
    }

    public function test_a_non_200_success_code_is_retried(): void
    {
        $website = new Website(['domain' => 'example.com', 'username' => 'admin', 'application_password' => 'secret']);
        Http::preventStrayRequests();
        Http::fake([
            'https://example.com/wp-json/wp-autoops/v1/status' => Http::sequence()
                ->pushStatus(201)
                ->push(['success' => true, 'data' => ['wordpress' => ['version' => '7.1.2']]], 200),
        ]);

        $result = $this->inspector()->inspect($website);

        Http::assertSentCount(2);
        $this->assertSame(200, $result['status_code']);
        $this->assertSame('7.1.2', $result['wordpress_version']);
    }

    public function test_connection_failures_are_attempted_three_times_and_return_no_status(): void
    {
        $website = new Website(['domain' => 'example.com', 'username' => 'admin', 'application_password' => 'secret']);
        Http::preventStrayRequests();
        Http::fake([
            'https://example.com/wp-json/wp-autoops/v1/status' => Http::sequence()
                ->pushFailedConnection()
                ->pushFailedConnection()
                ->pushFailedConnection(),
        ]);

        $result = $this->inspector()->inspect($website);

        Http::assertSentCount(3);
        $this->assertNull($result['status_code']);
        $this->assertSame('The site could not be reached after three attempts.', $result['check_error']);
    }

    private function inspector(): WebsiteInspector
    {
        return new class extends WebsiteInspector
        {
            protected function publicAddress(string $domain): ?string
            {
                return '93.184.215.14';
            }

            protected function certificateExpiry(string $domain, string $address): ?\DateTimeImmutable
            {
                return new \DateTimeImmutable('2027-01-10');
            }
        };
    }
}
