<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Connectors;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\GitHubConnector;
use Metribay\Core\Connectors\GitHubReleasesConnector;
use Metribay\Core\Connectors\ProviderRequestLimit;
use Metribay\Core\Connectors\SourceConfiguration;
use Metribay\Core\Services\ProviderCatalog;

require_once __DIR__.'/FixtureTestCase.php';

final class GitHubReleasesConnectorTest extends FixtureTestCase
{
    public function test_public_repositories_sum_asset_downloads_without_a_token_and_skip_drafts(): void
    {
        Http::fake(function (Request $request) {
            $this->assertFalse($request->hasHeader('Authorization'));
            $this->assertSame(GitHubConnector::API_VERSION, $request->header('X-GitHub-Api-Version')[0]);
            if (str_contains($request->url(), '/releases')) {
                return Http::response([
                    $this->release(1, [['id' => 11, 'download_count' => 10], ['id' => 12, 'download_count' => 5]]),
                    $this->release(2, [['id' => 21, 'download_count' => 100]], draft: true),
                    $this->release(3, [['id' => 31, 'download_count' => 3]], prerelease: true),
                ]);
            }

            return Http::response(['full_name' => 'Example/Repo']);
        });
        $result = $this->collect();
        $this->assertSame([['metric' => 'release_downloads', 'date' => '2026-10-25', 'value' => 18.0, 'is_partial' => false]], $result->daily);
        $this->assertSame(['2026-10-25' => ['release_downloads']], $result->replaceDailyDates);
        Http::assertSentCount(2);
    }

    public function test_a_token_is_sent_as_a_bearer_and_pages_are_summed(): void
    {
        Http::fake(function (Request $request) {
            $this->assertSame('Bearer synthetic-token', $request->header('Authorization')[0]);
            if (! str_contains($request->url(), '/releases')) {
                return Http::response(['full_name' => 'example/repo']);
            }
            $page = $this->page($request);
            $count = $page === 1 ? 100 : 1;
            $rows = [];
            for ($index = 0; $index < $count; $index++) {
                $id = (($page - 1) * 100) + $index + 1;
                $rows[] = $this->release($id, [['id' => $id + 10000, 'download_count' => 1]]);
            }

            return Http::response($rows);
        });
        $result = $this->collect(['token' => 'synthetic-token']);
        $this->assertSame(101.0, $result->daily[0]['value']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/releases') && $this->page($request) === 2);
    }

    public function test_a_missing_repository_is_not_stored_as_zero(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);
        $this->expectException(ConnectorException::class);
        $this->expectExceptionMessage('could not find this repository');
        try {
            $this->collect();
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_rate_limits_fail_without_a_partial_total(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/releases')) {
                return Http::response('', 429);
            }

            return Http::response(['full_name' => 'example/repo']);
        });
        $this->expectException(ProviderRequestLimit::class);
        $this->collect();
    }

    public function test_a_full_final_page_rejects_the_collection(): void
    {
        Http::fake(function (Request $request) {
            if (! str_contains($request->url(), '/releases')) {
                return Http::response(['full_name' => 'example/repo']);
            }
            $page = $this->page($request);
            $rows = [];
            for ($index = 0; $index < 100; $index++) {
                $id = (($page - 1) * 100) + $index + 1;
                $rows[] = $this->release($id, []);
            }

            return Http::response($rows);
        });
        $this->expectException(ConnectorException::class);
        $this->expectExceptionMessage('pagination limit');
        $this->collect();
    }

    public function test_catalog_accepts_a_repository_and_an_optional_token_account(): void
    {
        $this->assertSame(['repository' => 'example/repo'], ProviderCatalog::source('github_releases', ['repository' => 'example/repo'], 'UTC'));
        $this->assertSame(['token' => 'synthetic-token'], ProviderCatalog::credentials('github_releases', ['token' => ' synthetic-token ']));
        $this->assertFalse(ProviderCatalog::requiresConnection('github_releases'));
        $this->assertTrue(ProviderCatalog::connectionOptional('github_releases'));
        $this->assertSame(['release_downloads'], ProviderCatalog::metricKeys('github_releases'));
    }

    private function page(Request $request): int
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return (int) ($query['page'] ?? 0);
    }

    /** @param  list<array<string, mixed>>  $assets
     * @return array<string, mixed>
     */
    private function release(int $id, array $assets, bool $draft = false, bool $prerelease = false): array
    {
        return ['id' => $id, 'draft' => $draft, 'prerelease' => $prerelease, 'assets' => $assets];
    }

    /** @param  array<string, mixed>  $credentials */
    private function collect(array $credentials = []): \Metribay\Core\Connectors\CollectionResult
    {
        return (new GitHubReleasesConnector($this->http()))->collect(
            new SourceConfiguration('github_releases', ['repository' => 'example/repo'], 'UTC'),
            $credentials,
            $this->now(),
        );
    }
}
