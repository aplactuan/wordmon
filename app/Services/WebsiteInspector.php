<?php

namespace App\Services;

use App\Models\Website;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class WebsiteInspector
{
    /**
     * @return array{status_code: int|null, wordpress_version: string|null, ssl_expires_at: \DateTimeImmutable|null, checked_at: CarbonImmutable, check_error: string|null}
     */
    public function inspect(Website $website): array
    {
        $result = [
            'status_code' => null,
            'wordpress_version' => null,
            'ssl_expires_at' => null,
            'checked_at' => now(),
            'check_error' => null,
        ];

        try {
            $address = $this->publicAddress($website->domain);

            if ($address === null) {
                $result['check_error'] = 'Domain does not resolve to a public address.';

                return $result;
            }

            $response = null;

            for ($attempt = 0; $attempt < 3; $attempt++) {
                try {
                    $response = Http::connectTimeout(3)
                        ->timeout(8)
                        ->acceptJson()
                        ->withBasicAuth($website->username, $website->application_password)
                        ->withOptions($this->requestOptions($website->domain, $address))
                        ->get('https://'.$website->domain.'/wp-json/wp-autoops/v1/status');

                    if ($response->status() === 200) {
                        break;
                    }
                } catch (ConnectionException) {
                    // A later attempt can still recover from a temporary connection failure.
                }
            }

            if ($response === null) {
                $result['check_error'] = 'The site could not be reached after three attempts.';

                return $result;
            }

            $result['status_code'] = $response->status();

            if (in_array($response->status(), [401, 403], true)) {
                $result['check_error'] = 'WordPress credentials could not be verified.';
            } elseif ($response->status() !== 200) {
                $result['check_error'] = 'Status endpoint returned HTTP '.$response->status().'.';

                return $result;
            }

            if ($result['check_error'] !== null) {
                return $result;
            }

            $version = $response->json('data.wordpress.version');

            if ($response->json('success') !== true || ! is_string($version) || trim($version) === '' || mb_strlen($version) > 50) {
                $result['check_error'] = 'Status endpoint returned an invalid response.';
            } else {
                $result['wordpress_version'] = $version;
            }

            $result['ssl_expires_at'] = $this->certificateExpiry($website->domain, $address);

            if ($result['ssl_expires_at'] === null && $result['check_error'] === null) {
                $result['check_error'] = 'SSL certificate could not be verified.';
            }
        } catch (Throwable) {
            $result['check_error'] = 'The site could not be checked. Please verify the domain and try again.';
        }

        $result['checked_at'] = now();

        return $result;
    }

    protected function publicAddress(string $domain): ?string
    {
        $records = dns_get_record($domain, DNS_A | DNS_AAAA);

        if ($records === false || $records === []) {
            return null;
        }

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if ($address === null || filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
        }

        return $records[0]['ip'] ?? $records[0]['ipv6'] ?? null;
    }

    /**
     * @return array{allow_redirects: false, curl: array<int, array<int, string>>}
     */
    private function requestOptions(string $domain, string $address): array
    {
        $resolvedAddress = str_contains($address, ':') ? '['.$address.']' : $address;

        return [
            'allow_redirects' => false,
            'curl' => [CURLOPT_RESOLVE => ["{$domain}:443:{$resolvedAddress}"]],
        ];
    }

    protected function certificateExpiry(string $domain, string $address): ?\DateTimeImmutable
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $domain,
        ]]);

        $socketAddress = str_contains($address, ':') ? '['.$address.']' : $address;
        $socket = @stream_socket_client('ssl://'.$socketAddress.':443', $errorCode, $errorMessage, 5, STREAM_CLIENT_CONNECT, $context);

        if ($socket === false) {
            return null;
        }

        $parameters = stream_context_get_params($socket);
        fclose($socket);
        $certificate = $parameters['options']['ssl']['peer_certificate'] ?? null;
        $details = $certificate ? openssl_x509_parse($certificate) : false;

        return isset($details['validTo_time_t'])
            ? new \DateTimeImmutable('@'.$details['validTo_time_t'])
            : null;
    }
}
