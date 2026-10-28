<?php

namespace Metribay\Core\Tests\Connectors;

use Illuminate\Support\Facades\Http;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\ResourceDiscovery;

class ResourceDiscoveryTest extends FixtureTestCase
{
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
