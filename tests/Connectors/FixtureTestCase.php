<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Connectors;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Metribay\Core\Connectors\SafeHttpClient;
use Tests\TestCase;

abstract class FixtureTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    protected function http(int $maxBytes = 8_388_608): SafeHttpClient
    {
        return new SafeHttpClient(static fn (string $host): array => ['93.184.216.34'], $maxBytes);
    }

    protected function now(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-10-25T10:00:00Z');
    }

    /** @return array{private: string, public: string} */
    protected static function signingKeys(): array
    {
        static $keys;
        if ($keys === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            if ($key === false) {
                throw new \RuntimeException('Synthetic fixture-key generation failed.');
            }
            openssl_pkey_export($key, $private);
            $details = openssl_pkey_get_details($key);
            $keys = ['private' => $private, 'public' => $details['key']];
        }

        return $keys;
    }

    /** @return array<string, mixed> */
    protected function googleCredentials(): array
    {
        return ['service_account' => ['type' => 'service_account', 'client_email' => 'fixture@metribay-example.iam.gserviceaccount.com',
            'private_key' => self::signingKeys()['private'], 'private_key_id' => 'synthetic-key-only',
            'project_id' => 'metribay-example', 'token_uri' => 'https://oauth2.googleapis.com/token']];
    }

    /** @param list<array<string, mixed>> $rows @return array<string, float> */
    protected function valuesOn(array $rows, string $date): array
    {
        $values = [];
        foreach ($rows as $row) {
            if ($row['date'] === $date) {
                $values[$row['metric']] = $row['value'];
            }
        }
        ksort($values);

        return $values;
    }
}
