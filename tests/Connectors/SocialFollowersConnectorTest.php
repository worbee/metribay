<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Connectors;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Connectors\BlueskyConnector;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\MastodonConnector;
use Metribay\Core\Connectors\ProviderRequestLimit;
use Metribay\Core\Connectors\SocialAccount;
use Metribay\Core\Connectors\SourceConfiguration;
use Metribay\Core\Services\ProviderCatalog;

require_once __DIR__.'/FixtureTestCase.php';

final class SocialFollowersConnectorTest extends FixtureTestCase
{
    public function test_bluesky_records_todays_follower_count_without_credentials(): void
    {
        Http::fake(function (Request $request) {
            $this->assertFalse($request->hasHeader('Authorization'));
            $this->assertSame('https://public.api.bsky.app/xrpc/app.bsky.actor.getProfile?actor=example.bsky.social', $request->url());

            return Http::response(['did' => 'did:plc:abcdefghijklmnopqrstuvwx', 'handle' => 'Example.bsky.social', 'followersCount' => 1234, 'followsCount' => 9]);
        });
        $result = $this->bluesky('example.bsky.social');
        $this->assertSame([['metric' => 'followers', 'date' => '2026-10-25', 'value' => 1234.0, 'is_partial' => false]], $result->daily);
        $this->assertSame(['2026-10-25' => ['followers']], $result->replaceDailyDates);
        Http::assertSentCount(1);
    }

    public function test_bluesky_accepts_a_did_and_records_an_explicit_zero(): void
    {
        Http::fake(['public.api.bsky.app/*' => Http::response(['did' => 'did:plc:abcdefghijklmnopqrstuvwx', 'handle' => 'renamed.example', 'followersCount' => 0])]);
        $this->assertSame(0.0, $this->bluesky('did:plc:abcdefghijklmnopqrstuvwx')->daily[0]['value']);
    }

    public function test_bluesky_failures_never_store_a_count(): void
    {
        $current = null;
        Http::fake(function () use (&$current) {
            return $current;
        });
        foreach ([
            [Http::response(['error' => 'InvalidRequest', 'message' => 'Profile not found'], 400), ConnectorException::class, 'could not find this account'],
            [Http::response('', 429), ProviderRequestLimit::class, null],
            [Http::response(['did' => 'did:plc:abcdefghijklmnopqrstuvwx', 'handle' => 'someone-else.bsky.social', 'followersCount' => 5]), ConnectorException::class, 'different account'],
            [Http::response(['did' => 'did:plc:abcdefghijklmnopqrstuvwx', 'handle' => 'example.bsky.social']), ConnectorException::class, 'did not report a follower count'],
            [Http::response(['did' => 'did:plc:abcdefghijklmnopqrstuvwx', 'handle' => 'example.bsky.social', 'followersCount' => -1]), ConnectorException::class, 'invalid metric value'],
            [Http::response(['did' => 'did:plc:abcdefghijklmnopqrstuvwx', 'handle' => 'example.bsky.social', 'followersCount' => 1.5]), ConnectorException::class, 'invalid count'],
            [Http::response('not json'), ConnectorException::class, 'invalid JSON'],
            [Http::response('', 500), ConnectorException::class, 'HTTP 500'],
        ] as [$response, $exception, $message]) {
            $current = $response;
            try {
                $this->bluesky('example.bsky.social');
                $this->fail('Expected '.$exception);
            } catch (ConnectorException|ProviderRequestLimit $error) {
                $this->assertInstanceOf($exception, $error);
                if ($message !== null) {
                    $this->assertStringContainsString($message, $error->getMessage());
                }
            }
        }
    }

    public function test_mastodon_reads_the_account_from_its_own_server(): void
    {
        Http::fake(function (Request $request) {
            $this->assertFalse($request->hasHeader('Authorization'));
            $this->assertSame('https://social.example/api/v1/accounts/lookup?acct=example', $request->url());

            return Http::response(['id' => '1', 'username' => 'Example', 'acct' => 'Example', 'followers_count' => 321]);
        });
        $result = $this->mastodon('example@social.example');
        $this->assertSame([['metric' => 'followers', 'date' => '2026-10-25', 'value' => 321.0, 'is_partial' => false]], $result->daily);
        $this->assertSame(['2026-10-25' => ['followers']], $result->replaceDailyDates);
    }

    public function test_mastodon_failures_never_store_a_count(): void
    {
        $current = null;
        Http::fake(function () use (&$current) {
            return $current;
        });
        foreach ([
            [Http::response(['error' => 'Record not found'], 404), ConnectorException::class, 'could not find the account'],
            [Http::response(['error' => 'This API requires an authenticated user'], 401), ConnectorException::class, 'does not allow public account lookups'],
            [Http::response('', 429), ProviderRequestLimit::class, null],
            [Http::response(['username' => 'example', 'acct' => 'example@elsewhere.example', 'followers_count' => 4]), ConnectorException::class, 'different account'],
            [Http::response(['username' => 'other', 'acct' => 'other', 'followers_count' => 4]), ConnectorException::class, 'different account'],
            [Http::response(['username' => 'example', 'acct' => 'example']), ConnectorException::class, 'did not report a follower count'],
            [Http::response(['username' => 'example', 'acct' => 'example', 'followers_count' => '12abc']), ConnectorException::class, 'invalid metric value'],
        ] as [$response, $exception, $message]) {
            $current = $response;
            try {
                $this->mastodon('example@social.example');
                $this->fail('Expected '.$exception);
            } catch (ConnectorException|ProviderRequestLimit $error) {
                $this->assertInstanceOf($exception, $error);
                if ($message !== null) {
                    $this->assertStringContainsString($message, $error->getMessage());
                }
            }
        }
    }

    public function test_mastodon_never_contacts_a_private_or_invalid_server(): void
    {
        Http::fake();
        foreach (['example@localhost', 'example@printer.local', 'example', 'https://social.example/users/example', 'a@b@social.example'] as $account) {
            try {
                $this->mastodon($account);
                $this->fail('Expected a rejected account: '.$account);
            } catch (ConnectorException $error) {
                $this->assertStringContainsString('Use a Mastodon account', $error->getMessage());
            }
        }
        Http::assertNothingSent();
    }

    public function test_accounts_are_normalized_from_handles_and_profile_links(): void
    {
        $this->assertSame('example.bsky.social', SocialAccount::bluesky(' @Example.bsky.social '));
        $this->assertSame('example.com', SocialAccount::bluesky('https://bsky.app/profile/Example.com'));
        $this->assertSame('did:plc:abcdefghijklmnopqrstuvwx', SocialAccount::bluesky('https://bsky.app/profile/did:plc:abcdefghijklmnopqrstuvwx'));
        $this->assertSame('name@mastodon.social', SocialAccount::mastodon('@Name@Mastodon.social'));
        $this->assertSame('name@social.example', SocialAccount::mastodon('https://social.example/@Name'));
        foreach (['', 'no spaces.bsky.social', 'localhost', 'host.local', 'did:plc:short', 'https://bsky.app/profile/'] as $invalid) {
            try {
                SocialAccount::bluesky($invalid);
                $this->fail('Expected an invalid Bluesky account: '.$invalid);
            } catch (ConnectorException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_catalog_needs_no_account_and_stores_the_normalized_account(): void
    {
        foreach (['bluesky' => ['@Example.bsky.social', 'example.bsky.social'], 'mastodon' => ['https://social.example/@Example', 'example@social.example']] as $kind => [$input, $stored]) {
            $this->assertSame(['account' => $stored], ProviderCatalog::source($kind, ['account' => $input], 'UTC'));
            $this->assertFalse(ProviderCatalog::requiresConnection($kind));
            $this->assertFalse(ProviderCatalog::connectionOptional($kind));
            $this->assertSame(['followers'], ProviderCatalog::metricKeys($kind));
            try {
                ProviderCatalog::source($kind, ['account' => $input], 'Europe/Ljubljana');
                $this->fail('Expected UTC only.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('reporting_timezone', $error->errors());
            }
            try {
                ProviderCatalog::source($kind, ['account' => 'not an account'], 'UTC');
                $this->fail('Expected an invalid account.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('config.account', $error->errors());
            }
            try {
                ProviderCatalog::credentials($kind, ['api_key' => 'synthetic']);
                $this->fail('Expected no connected account.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('kind', $error->errors());
            }
        }
    }

    private function bluesky(string $account): CollectionResult
    {
        return (new BlueskyConnector($this->http()))->collect(new SourceConfiguration('bluesky', ['account' => $account], 'UTC'), [], $this->now());
    }

    private function mastodon(string $account): CollectionResult
    {
        return (new MastodonConnector($this->http()))->collect(new SourceConfiguration('mastodon', ['account' => $account], 'UTC'), [], $this->now());
    }
}
