<?php

namespace Metribay\Core\Tests\Connectors;

use Illuminate\Support\Facades\Http;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\ResourceDiscovery;
use Metribay\Core\Connectors\UmamiDiscoveryException;

class ResourceDiscoveryTest extends FixtureTestCase
{
    public function test_umami_includes_team_websites_and_deduplicates_personal_sites(): void
    {
        Http::fakeSequence()->push(['token' => 'fixture'])
            ->push(['data' => [['id' => 'personal', 'name' => 'Personal']], 'count' => 1])
            ->push(['data' => [['id' => 'team/one']], 'count' => 1])
            ->push(['data' => [['id' => 'personal'], ['id' => 'team-site', 'name' => 'Team &amp; Site', 'domain' => 'example.com']], 'count' => 2]);
        $rows = (new ResourceDiscovery($this->http()))->discover('umami', ['base_url' => 'https://analytics.example.com', 'username' => 'fixture', 'password' => ' secret ']);
        $this->assertSame(['personal', 'team-site'], array_column($rows, 'id'));
        $this->assertSame('Team & Site', $rows[1]['name']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/teams/team%2Fone/websites'));
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['password'] === ' secret ');
    }

    public function test_umami_paginates_team_websites(): void
    {
        Http::fakeSequence()->push(['token' => 'fixture'])->push(['data' => [], 'count' => 0])
            ->push(['data' => [['id' => 'team']], 'count' => 1])
            ->push(['data' => [['id' => 'first']], 'count' => 2])->push(['data' => [['id' => 'last']], 'count' => 2]);
        $rows = (new ResourceDiscovery($this->http()))->discover('umami', ['base_url' => 'https://analytics.example.com']);
        $this->assertSame(['first', 'last'], array_column($rows, 'id'));
        Http::assertSentCount(5);
    }

    public function test_umami_team_access_failure_does_not_return_partial_success(): void
    {
        Http::fakeSequence()->push(['token' => 'fixture'])->push(['data' => [['id' => 'personal']], 'count' => 1])
            ->push(['data' => [['id' => 'team']], 'count' => 1])->push(['secret' => 'provider-private'], 403);
        $this->expectExceptionMessage('Umami denied access. Check this account’s website and team permissions. (HTTP 403)');
        (new ResourceDiscovery($this->http()))->discover('umami', ['base_url' => 'https://analytics.example.com']);
    }

    public function test_umami_invalid_list_is_not_an_empty_success(): void
    {
        Http::fakeSequence()->push(['token' => 'fixture'])->push(['unexpected' => 'body']);
        $this->expectExceptionMessage('Umami returned an invalid resource list.');
        (new ResourceDiscovery($this->http()))->discover('umami', ['base_url' => 'https://analytics.example.com']);
    }

    public function test_umami_empty_personal_and_team_lists_are_valid(): void
    {
        Http::fakeSequence()->push(['token' => 'fixture'])->push(['data' => [], 'count' => 0])->push(['data' => [], 'count' => 0]);
        $this->assertSame([], (new ResourceDiscovery($this->http()))->discover('umami', ['base_url' => 'https://analytics.example.com']));
    }

    public function test_umami_incomplete_pagination_is_bounded(): void
    {
        $sequence = Http::fakeSequence()->push(['token' => 'fixture']);
        for ($page = 1; $page <= 20; $page++) {
            $sequence->push(['data' => [['id' => 'site-'.$page]], 'count' => 9999]);
        }
        try {
            (new ResourceDiscovery($this->http()))->discover('umami', ['base_url' => 'https://analytics.example.com']);
            $this->fail('Incomplete pagination must not return success.');
        } catch (UmamiDiscoveryException $exception) {
            $this->assertStringContainsString('could not be fully loaded', $exception->getMessage());
        }
        Http::assertSentCount(21);
    }

    public function test_repository_pagination_is_complete(): void
    {
        $first = array_fill(0, 100, ['full_name' => 'fixture/repository']);
        Http::fakeSequence()->push($first)->push([['full_name' => 'fixture/last']]);
        $rows = (new ResourceDiscovery($this->http()))->discover('github', ['token' => 'fixture']);
        $this->assertCount(101, $rows);
        $this->assertSame('fixture/last', $rows[100]['id']);
        Http::assertSentCount(2);
    }

    public function test_monitor_auth_failure_is_not_an_empty_success(): void
    {
        Http::fakeSequence()->push(['stat' => 'fail', 'error' => ['type' => 'invalid_parameter']]);
        $this->expectException(ConnectorException::class);
        (new ResourceDiscovery($this->http()))->discover('uptimerobot', ['api_key' => 'fixture']);
    }

    public function test_search_properties_preserve_domain_property_ids(): void
    {
        Http::fakeSequence()->push(['access_token' => 'fixture', 'expires_in' => 3600])->push(['siteEntry' => [['siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteOwner']]]);
        $rows = (new ResourceDiscovery($this->http()))->discover('gsc', $this->googleCredentials());
        $this->assertSame(['id' => 'sc-domain:example.com', 'name' => 'sc-domain:example.com', 'domain' => 'example.com', 'timezone' => 'America/Los_Angeles'], $rows[0]);
    }

    public function test_analytics_lists_without_fetching_metadata_for_every_property(): void
    {
        Http::fakeSequence()->push(['access_token' => 'fixture', 'expires_in' => 3600])->push(['accountSummaries' => [['propertySummaries' => array_fill(0, 100, ['property' => 'properties/123', 'displayName' => 'Example'])]]]);
        $rows = (new ResourceDiscovery($this->http()))->discover('ga4', $this->googleCredentials());
        $this->assertCount(100, $rows);
        $this->assertSame('123', $rows[0]['id']);
        Http::assertSentCount(2);
    }

    public function test_selected_analytics_property_resolves_reporting_timezone(): void
    {
        Http::fakeSequence()->push(['access_token' => 'fixture', 'expires_in' => 3600])->push(['timeZone' => 'Europe/Berlin']);
        $this->assertSame('Europe/Berlin', (new ResourceDiscovery($this->http()))->googleTimezone($this->googleCredentials(), '123'));
    }

    public function test_public_plugin_names_are_plain_text(): void
    {
        Http::fakeSequence()->push(['plugins' => [['slug' => 'fixture-plugin', 'name' => 'Example &amp; Plugin']]]);
        $rows = (new ResourceDiscovery($this->http()))->discover('wporg', [], 'Example');
        $this->assertSame('Example & Plugin', $rows[0]['name']);
    }
}
