<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Connectors;

use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Metribay\Core\Connectors\BoundedResponseStream;
use Metribay\Core\Connectors\Connector;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\ConnectorRegistry;
use Metribay\Core\Connectors\SafeHttpClient;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__.'/FixtureTestCase.php';

final class SafeHttpClientTest extends FixtureTestCase
{
    public static function unsafeUrls(): array
    {
        return array_map(static fn (string $url): array => [$url], [
            'http://metrics.example.com/api', 'file:///etc/passwd', 'https://127.0.0.1/api', 'https://[::1]/api',
            'https://[::ffff:127.0.0.1]/api', 'https://2130706433/api', 'https://0x7f000001/api',
            'https://localhost/api', 'https://service.internal/api', 'https://metrics.example.com:8443/api',
            'https://username:password@metrics.example.com/api', "https://metrics.example.com\n/api",
            'https://metrics.example.com\\@127.0.0.1/', 'https://metrics.example.com/#secret',
        ]);
    }

    #[DataProvider('unsafeUrls')]
    public function test_unsafe_urls_fail_before_any_request(string $url): void
    {
        Http::fake();
        try {
            $this->http()->request('GET', $url);
            $this->fail('Unsafe endpoint was accepted.');
        } catch (ConnectorException $exception) {
            $this->assertStringNotContainsString('password', $exception->getMessage());
            Http::assertNothingSent();
        }
    }

    public static function unsafeAddresses(): array
    {
        return array_map(static fn (string $address): array => [$address], ['10.2.3.4', '127.1.2.3', '169.254.169.254',
            '172.16.1.1', '192.168.1.1', '100.64.0.1', '192.0.2.1', '198.18.0.1', '203.0.113.1', '224.0.0.1',
            '240.1.1.1', '::', '::1', '::ffff:8.8.8.8', 'fc00::1', 'fe80::1', 'ff02::1', '2001:db8::1',
            '2002:7f00:1::1', '64:ff9b::a00:1', '3fff::1']);
    }

    #[DataProvider('unsafeAddresses')]
    public function test_all_dns_answers_must_be_public(string $address): void
    {
        Http::fake();
        $http = new SafeHttpClient(static fn (string $host): array => ['8.8.8.8', $address]);
        $this->expectException(ConnectorException::class);
        try {
            $http->request('GET', 'https://metrics.example.com/api');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_dns_is_revalidated_and_the_checked_address_is_pinned_with_transport_guards(): void
    {
        $resolutions = 0;
        $seen = [];
        $http = new SafeHttpClient(function (string $host) use (&$resolutions): array {
            $this->assertSame('metrics.example.com', $host);
            $resolutions++;

            return $resolutions === 1 ? ['8.8.8.8'] : ['1.1.1.1'];
        });
        Http::fake(function (Request $request, array $options) use (&$seen) {
            $seen[] = $options;
            $this->assertSame('Metribay/0.1 (+https://metribay.com)', $request->header('User-Agent')[0]);

            return Http::response(['ok' => true]);
        });
        $http->request('GET', 'https://metrics.example.com/api');
        $http->request('GET', 'https://metrics.example.com/api');
        $this->assertSame(2, $resolutions);
        $this->assertSame(['metrics.example.com:443:8.8.8.8'], $seen[0]['curl'][CURLOPT_RESOLVE]);
        $this->assertSame(['metrics.example.com:443:1.1.1.1'], $seen[1]['curl'][CURLOPT_RESOLVE]);
        $this->assertFalse($seen[0]['allow_redirects']);
        $this->assertSame('', $seen[0]['proxy']);
        $this->assertTrue($seen[0]['verify']);
        $this->assertTrue($seen[0]['curl'][CURLOPT_FRESH_CONNECT]);
        $this->assertTrue($seen[0]['curl'][CURLOPT_FORBID_REUSE]);
        $this->assertSame(30, $seen[0]['timeout']);
    }

    public function test_native_curl_factory_accepts_the_guarded_transport_options_without_opening_a_socket(): void
    {
        Http::fake(function (Request $request, array $options) {
            $factory = new CurlFactory(1);
            $easy = $factory->create($request->toPsrRequest(), $options);
            $this->assertInstanceOf(\CurlHandle::class, $easy->handle);
            $this->assertSame('https://metrics.example.com/api', curl_getinfo($easy->handle, CURLINFO_EFFECTIVE_URL));
            $factory->release($easy);
            $factory->close();

            return Http::response(['transport' => 'constructed']);
        });
        $this->assertSame('constructed', $this->http()->request('GET', 'https://metrics.example.com/api')->json('transport'));
    }

    public function test_an_ipv6_public_address_is_pinned_in_brackets(): void
    {
        $http = new SafeHttpClient(static fn (string $host): array => ['2606:4700:4700::1111']);
        Http::fake(function (Request $request, array $options) {
            $this->assertSame(['metrics.example.com:443:[2606:4700:4700::1111]'], $options['curl'][CURLOPT_RESOLVE]);

            return Http::response('{}');
        });
        $http->request('GET', 'https://metrics.example.com/api');
    }

    public function test_redirects_are_rejected_without_following_the_location(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/credentials'])]);
        $this->expectException(ConnectorException::class);
        try {
            $this->http()->request('GET', 'https://metrics.example.com/api');
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_callers_cannot_override_transport_options_or_host(): void
    {
        Http::fake();
        foreach ([['verify' => false], ['curl' => []], ['headers' => ['Host' => '127.0.0.1']], ['headers' => ['Authorization' => "secret\r\nHost: localhost"]]] as $options) {
            try {
                $this->http()->request('GET', 'https://metrics.example.com/api', $options);
                $this->fail('Unsafe options were accepted.');
            } catch (ConnectorException $exception) {
                $this->assertStringNotContainsString('secret', $exception->getMessage());
            }
        }
        Http::assertNothingSent();
    }

    public function test_failed_requests_do_not_expose_urls_keys_or_provider_text(): void
    {
        Http::fake(static function () {
            throw new \RuntimeException('https://ssl.bing.com/?apikey=secret-a%26b raw secret-a&b');
        });
        try {
            $this->http()->request('GET', 'https://ssl.bing.com/webmaster/api', ['query' => ['apikey' => 'secret-a&b']]);
            $this->fail('Expected transport failure.');
        } catch (ConnectorException $exception) {
            $this->assertStringNotContainsString('secret', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_fake_and_real_response_sinks_have_a_decoded_byte_limit(): void
    {
        Http::fake(['*' => Http::response(str_repeat('x', 129))]);
        try {
            $this->http(128)->request('GET', 'https://metrics.example.com/api');
            $this->fail('Oversized fake response was accepted.');
        } catch (ConnectorException $exception) {
            $this->assertStringContainsString('size limit', $exception->getMessage());
        }
        $stream = new BoundedResponseStream(Utils::streamFor(''), 8);
        $stream->write('12345678');
        $this->expectException(ConnectorException::class);
        $stream->write('9');
    }

    public function test_declared_content_length_is_checked_before_body_is_received(): void
    {
        Http::fake(function (Request $request, array $options) {
            ($options['on_headers'])(new Response(200, ['Content-Length' => '129']));

            return Http::response('');
        });
        $this->expectException(ConnectorException::class);
        $this->http(128)->request('GET', 'https://metrics.example.com/api');
    }

    public function test_the_registry_has_every_supported_provider_and_rejects_unknown_kinds(): void
    {
        $registry = new ConnectorRegistry($this->http());
        $this->assertSame(['umami', 'plausible', 'matomo', 'ga4', 'gsc', 'bing', 'wporg', 'github', 'github_releases', 'feed', 'sitemap', 'uptimerobot', 'pagespeed', 'azure_devops', 'paddle'], $registry->kinds());
        foreach ($registry->kinds() as $kind) {
            $this->assertInstanceOf(Connector::class, $registry->for($kind));
        }
        $this->expectException(ConnectorException::class);
        $registry->for('unknown');
    }
}
