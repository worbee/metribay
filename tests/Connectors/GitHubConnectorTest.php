<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Connectors;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\GitHubConnector;
use Metribay\Core\Connectors\SourceConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__.'/FixtureTestCase.php';

final class GitHubConnectorTest extends FixtureTestCase
{
    public function test_activity_pages_use_committer_and_merge_dates_without_double_counting_merge_churn(): void
    {
        $this->githubFake(commits: [
            '' => $this->page([$this->commit('ordinary'), $this->commit('merge', parents: 2, additions: 900)], true, 'next-commits'),
            'next-commits' => $this->page([$this->commit('root', '2026-10-25T09:00:00Z', 0, 20, 0)]),
        ], pulls: [
            '' => $this->page([$this->pull('older', '2026-10-24T08:00:00Z', '2026-10-25T09:45:00Z')], true, 'next-pulls'),
            'next-pulls' => $this->page([$this->pull('newer', '2026-10-25T09:00:00Z', '2026-10-25T09:30:00Z')]),
        ]);
        $result = $this->collect();
        $this->assertSame(['commits' => 2.0, 'loc_changed' => 13.0, 'prs_merged' => 1.0], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertSame(['commits' => 1.0, 'loc_changed' => 20.0, 'open_issues' => 7.0, 'prs_merged' => 1.0], $this->valuesOn($result->daily, '2026-10-25'));
        $this->assertSame(['commits' => 0.0, 'loc_changed' => 0.0, 'prs_merged' => 0.0], $this->valuesOn($result->daily, '2026-10-23'));
        foreach ($result->daily as $row) {
            $this->assertSame($row['metric'] !== 'open_issues' && $row['date'] === '2026-10-25', $row['is_partial']);
        }
        $this->assertSame(['2026-10-25' => ['open_issues']], $result->replaceDailyDates);
        $this->assertTrue($result->authoritativeAnnotations);
        Http::assertSentCount(6);
    }

    public function test_pr_pagination_cutoff_uses_updated_time_so_old_merges_do_not_hide_newer_merges(): void
    {
        $this->githubFake(pulls: [
            '' => $this->page([$this->pull('old-merge', '2026-01-01T00:00:00Z', '2026-10-25T09:00:00Z')], true, 'next'),
            'next' => $this->page([$this->pull('recent-merge', '2026-10-24T00:00:00Z', '2026-10-24T00:00:00Z')]),
        ]);
        $this->assertSame(1.0, $this->valuesOn($this->collect()->daily, '2026-10-24')['prs_merged']);
    }

    public function test_imported_commit_history_can_predate_github_repository_creation_and_from_is_not_an_incremental_cutoff(): void
    {
        $this->githubFake(info: ['createdAt' => '2026-10-25T08:00:00Z'], commits: ['' => $this->page([$this->commit('imported', '2026-10-23T12:00:00Z')])]);
        $result = (new GitHubConnector($this->http()))->collect(new SourceConfiguration('github', ['repository' => 'example/repo', 'days' => 3], 'UTC', $this->now()->startOfDay()), ['token' => 'synthetic-token'], $this->now());
        $this->assertSame(1.0, $this->valuesOn($result->daily, '2026-10-23')['commits']);
    }

    public function test_empty_repository_only_has_zero_activity_since_creation_and_disabled_issues_remove_current_snapshot(): void
    {
        $this->githubFake(info: ['isEmpty' => true, 'createdAt' => '2026-10-24T18:00:00Z', 'hasIssuesEnabled' => false, 'defaultBranchRef' => null]);
        $result = $this->collect();
        $this->assertCount(6, $result->daily);
        $this->assertSame([], $this->valuesOn($result->daily, '2026-10-23'));
        $this->assertArrayNotHasKey('open_issues', $this->valuesOn($result->daily, '2026-10-25'));
        $this->assertSame(['2026-10-25' => ['open_issues']], $result->replaceDailyDates);
        Http::assertSentCount(3);
    }

    public function test_an_old_draft_published_now_is_imported_and_all_release_pages_are_authoritative(): void
    {
        $draft = $this->release('draft');
        $draft['isDraft'] = true;
        $draft['publishedAt'] = null;
        $prerelease = $this->release('preview', '2026-01-01T00:00:00Z');
        $prerelease['isPrerelease'] = true;
        $this->githubFake(releases: ['' => $this->page([$draft, $prerelease], true, 'older-created'),
            'older-created' => $this->page([$this->release('released-today', '2026-10-25T08:00:00Z')])]);
        $result = $this->collect();
        $this->assertCount(2, $result->annotations);
        $this->assertSame('Release preview (prerelease)', $result->annotations[0]['title']);
        $this->assertSame('released-today', $result->annotations[1]['external_id']);
        $this->assertSame('2026-10-25T08:00:00+00:00', $result->annotations[1]['occurred_at']);
        $this->assertSame('Synthetic release notes', $result->annotations[1]['body']);
        $this->assertTrue($result->authoritativeAnnotations);
    }

    public static function paginationFaults(): array
    {
        return [['emptyPage'], ['noCursor'], ['repeatedCursor'], ['duplicateNode'], ['badFlag'], ['pageLimit']];
    }

    #[DataProvider('paginationFaults')]
    public function test_pagination_faults_reject_partial_totals(string $problem): void
    {
        $pages = ['' => $this->page([$this->commit('one')], true, 'next')];
        $config = [];
        switch ($problem) {
            case 'emptyPage': $pages[''] = $this->page([], true, 'next');
                break;
            case 'noCursor': $pages['']['pageInfo']['endCursor'] = null;
                break;
            case 'repeatedCursor': $pages['next'] = $this->page([$this->commit('two')], true, 'next');
                break;
            case 'duplicateNode': $pages['next'] = $this->page([$this->commit('one')]);
                break;
            case 'badFlag': $pages['']['pageInfo']['hasNextPage'] = 'true';
                break;
            case 'pageLimit': $config['max_pages'] = 1;
                break;
        }
        $this->githubFake(commits: $pages);
        $this->expectException(ConnectorException::class);
        $this->collect($config);
    }

    public static function malformedGraphql(): array
    {
        return [['partial'], ['noRepository'], ['wrongRepository'], ['negativeCount'], ['missingCount'], ['badTimestamp'], ['badOrdering'], ['unsafeReleaseUrl'], ['futureRelease']];
    }

    #[DataProvider('malformedGraphql')]
    public function test_malformed_or_partial_provider_data_never_produces_a_successful_result(string $problem): void
    {
        $info = [];
        $commits = [];
        $pulls = [];
        $releases = [];
        $override = null;
        switch ($problem) {
            case 'partial': $override = static fn () => ['errors' => [['message' => 'synthetic-provider-secret']], 'data' => ['repository' => []]];
                break;
            case 'noRepository': $override = static fn () => ['data' => ['repository' => null]];
                break;
            case 'wrongRepository': $info['nameWithOwner'] = 'another/repo';
                break;
            case 'negativeCount': $row = $this->commit();
                $row['additions'] = -1;
                $commits = ['' => $this->page([$row])];
                break;
            case 'missingCount': $row = $this->commit();
                unset($row['deletions']);
                $commits = ['' => $this->page([$row])];
                break;
            case 'badTimestamp': $commits = ['' => $this->page([$this->commit(date: '2026-10-24T12:00:00')])];
                break;
            case 'badOrdering': $pulls = ['' => $this->page([$this->pull('p', '2026-10-25T00:00:00Z', '2026-10-24T00:00:00Z')])];
                break;
            case 'unsafeReleaseUrl': $row = $this->release();
                $row['url'] = 'javascript:alert(1)';
                $releases = ['' => $this->page([$row])];
                break;
            case 'futureRelease': $releases = ['' => $this->page([$this->release('future', '2026-10-26T00:00:00Z')])];
                break;
        }
        $this->githubFake($commits, $pulls, $releases, $info, $override);
        try {
            $this->collect();
            $this->fail('Untrustworthy GitHub data was accepted.');
        } catch (ConnectorException $exception) {
            $this->assertStringNotContainsString('synthetic-provider-secret', $exception->getMessage());
        }
    }

    public function test_github_app_jwt_signature_claims_and_restricted_installation_scope_are_valid(): void
    {
        $claims = null;
        $this->githubFake(override: function (Request $request) use (&$claims) {
            if (str_contains($request->url(), '/access_tokens')) {
                [$header, $payload, $signature] = explode('.', substr($request->header('Authorization')[0], 7));
                $decode = static fn (string $value): string => base64_decode(strtr($value, '-_', '+/'));
                $claims = json_decode($decode($payload), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(1, openssl_verify($header.'.'.$payload, $decode($signature), self::signingKeys()['public'], OPENSSL_ALGO_SHA256));
                $this->assertSame(['repo'], $request['repositories']);
                $this->assertSame(['contents' => 'read', 'issues' => 'read', 'pull_requests' => 'read'], $request['permissions']);

                return Http::response(['token' => 'synthetic-installation-token', 'expires_at' => '2026-10-25T11:00:00Z'], 201);
            }

            return null;
        });
        $this->collect(credentials: ['client_id' => 'Iv23_synthetic', 'installation_id' => '123', 'private_key' => self::signingKeys()['private']]);
        $this->assertSame(['iss' => 'Iv23_synthetic', 'iat' => $this->now()->getTimestamp() - 60, 'exp' => $this->now()->getTimestamp() + 540], $claims);
        Http::assertSentCount(5);
    }

    public function test_an_invalidated_installation_token_is_renewed_exactly_once(): void
    {
        $tokens = $queries = 0;
        $this->githubFake(override: function (Request $request) use (&$tokens, &$queries) {
            if (str_contains($request->url(), '/access_tokens')) {
                $tokens++;

                return Http::response(['token' => 'synthetic-'.$tokens, 'expires_at' => '2026-10-25T11:00:00Z'], 201);
            }
            $queries++;
            if ($queries === 1) {
                return Http::response('secret-token', 401);
            }
            $this->assertSame('Bearer synthetic-2', $request->header('Authorization')[0]);

            return null;
        });
        $this->collect(credentials: ['client_id' => 'Iv23_synthetic', 'installation_id' => '123', 'private_key' => self::signingKeys()['private']]);
        $this->assertSame(2, $tokens);
        Http::assertSentCount(7);
    }

    public function test_persistent_401_and_non_json_errors_are_bounded_without_exposing_tokens(): void
    {
        Http::fake(['*' => Http::response('synthetic-private-token', 401)]);
        try {
            $this->collect();
            $this->fail('Invalid token was accepted.');
        } catch (ConnectorException $exception) {
            $this->assertStringNotContainsString('synthetic-private-token', $exception->getMessage());
            Http::assertSentCount(1);
        }
    }

    /** @return CollectionResult */
    private function collect(array $config = [], array $credentials = ['token' => 'synthetic-token'])
    {
        return (new GitHubConnector($this->http()))->collect(new SourceConfiguration('github', $config + ['repository' => 'example/repo', 'days' => 3]), $credentials, $this->now());
    }

    private function githubFake(array $commits = [], array $pulls = [], array $releases = [], array $info = [], ?callable $override = null): void
    {
        $info += ['nameWithOwner' => 'example/repo', 'createdAt' => '2026-01-01T00:00:00Z', 'isEmpty' => false,
            'hasIssuesEnabled' => true, 'issues' => ['totalCount' => 7], 'defaultBranchRef' => ['target' => ['oid' => 'default-branch-snapshot']]];
        $commits = $commits ?: ['' => $this->page([])];
        $pulls = $pulls ?: ['' => $this->page([])];
        $releases = $releases ?: ['' => $this->page([])];
        Http::fake(function (Request $request) use ($commits, $pulls, $releases, $info, $override) {
            $this->assertSame(GitHubConnector::API_VERSION, $request->header('X-GitHub-Api-Version')[0]);
            if ($override !== null && ($response = $override($request)) !== null) {
                return is_array($response) ? Http::response($response) : $response;
            }
            $this->assertSame('https://api.github.com/graphql', $request->url());
            $arguments = $request['variables'];
            $this->assertSame('example', $arguments['owner']);
            $this->assertSame('repo', $arguments['name']);
            $cursor = $arguments['after'] ?? '';
            if (str_contains($request['query'], 'RepositoryMetrics')) {
                $data = $info;
            } elseif (str_contains($request['query'], 'MergedPulls')) {
                $data = ['pullRequests' => $pulls[$cursor]];
            } elseif (str_contains($request['query'], 'CommitActivity')) {
                $this->assertSame('default-branch-snapshot', $arguments['oid']);
                $data = ['object' => ['history' => $commits[$cursor]]];
            } else {
                $data = ['releases' => $releases[$cursor]];
            }

            return Http::response(['data' => ['repository' => $data]]);
        });
    }

    private function page(array $nodes, bool $more = false, ?string $cursor = null): array
    {
        return ['nodes' => $nodes, 'pageInfo' => ['hasNextPage' => $more, 'endCursor' => $cursor]];
    }

    private function commit(string $oid = 'ordinary', string $date = '2026-10-24T12:00:00Z', int $parents = 1, int $additions = 10, int $deletions = 3): array
    {
        return ['oid' => $oid, 'committedDate' => $date, 'additions' => $additions, 'deletions' => $deletions, 'parents' => ['totalCount' => $parents]];
    }

    private function pull(string $id = 'pull-one', string $merged = '2026-10-24T12:00:00Z', string $updated = '2026-10-24T13:00:00Z'): array
    {
        return ['id' => $id, 'mergedAt' => $merged, 'updatedAt' => $updated];
    }

    private function release(string $id = 'release-one', string $published = '2026-10-24T12:00:00Z'): array
    {
        return ['id' => $id, 'name' => 'Release '.$id, 'tagName' => 'v1.0', 'description' => 'Synthetic release notes',
            'url' => 'https://github.com/example/repo/releases/tag/'.$id, 'publishedAt' => $published, 'isDraft' => false, 'isPrerelease' => false];
    }
}
