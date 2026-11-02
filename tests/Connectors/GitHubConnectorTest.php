<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Connectors;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\GitHubConnector;
use Metribay\Core\Connectors\GitHubDeploymentAccess;
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
        $this->assertSame(['github_release' => '2026-10-23T00:00:00+00:00', 'github_deployment' => null], $result->authoritativeEvents);
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

    public function test_open_issue_count_is_an_issues_only_current_snapshot_and_zero_is_recorded(): void
    {
        $this->githubFake(info: ['issues' => ['totalCount' => 0]], pulls: ['' => $this->page([$this->pull('merged', '2026-10-24T08:00:00Z', '2026-10-25T09:45:00Z')])]);
        $result = $this->collect();
        $issues = array_values(array_filter($result->daily, fn (array $row): bool => $row['metric'] === 'open_issues'));
        $this->assertSame([['metric' => 'open_issues', 'date' => '2026-10-25', 'value' => 0.0, 'is_partial' => false]], $issues);
        Http::assertSent(fn (Request $request): bool => str_contains($request['query'], 'issues(first: 1, states: OPEN) { totalCount }'));
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

    public function test_releases_are_read_until_a_margin_before_the_window_and_only_window_releases_become_events(): void
    {
        $draft = $this->release('draft', created: '2026-10-25T09:00:00Z');
        $draft['isDraft'] = true;
        $draft['publishedAt'] = null;
        $prerelease = $this->release('preview', '2026-10-24T09:00:00Z', '2026-09-25T00:00:00Z');
        $prerelease['isPrerelease'] = true;
        $this->githubFake(releases: [
            '' => $this->page([$draft, $this->release('old-draft', '2026-10-25T08:00:00Z', '2026-09-30T00:00:00Z'), $prerelease], true, 'older-created'),
            'older-created' => $this->page([$this->release('before-window', '2026-10-01T00:00:00Z', '2026-09-01T00:00:00Z')], true, 'never-read'),
        ]);
        $result = $this->collect();
        $this->assertSame(['old-draft', 'preview'], array_column($result->events, 'external_id'));
        $this->assertSame(['github_release', 'github_release'], array_column($result->events, 'origin'));
        $this->assertSame('2026-10-25T08:00:00+00:00', $result->events[0]['occurred_at']);
        $this->assertSame('Synthetic release notes', $result->events[0]['body']);
        $this->assertSame('Release preview (prerelease)', $result->events[1]['title']);
        $this->assertSame('2026-10-23T00:00:00+00:00', $result->authoritativeEvents['github_release']);
        Http::assertNotSent(fn (Request $request): bool => ($request['variables']['after'] ?? null) === 'never-read');
    }

    public function test_successful_deployments_to_chosen_environments_become_events(): void
    {
        $this->githubFake(deployments: ['' => $this->page([
            $this->deployment('tagged', 'Production', '2026-10-25T08:00:00Z', [['SUCCESS', '2026-10-25T08:05:00Z']], ['name' => 'v2.0.0', 'prefix' => 'refs/tags/']),
            $this->deployment('staging', 'staging', '2026-10-25T07:00:00Z', [['SUCCESS', '2026-10-25T07:05:00Z']]),
            $this->deployment('failed', 'production', '2026-10-24T09:00:00Z', [['FAILURE', '2026-10-24T09:05:00Z']]),
            $this->deployment('replaced', 'production', '2026-10-24T08:00:00Z', [['INACTIVE', '2026-10-25T08:05:00Z'], ['SUCCESS', '2026-10-24T08:04:00Z'], ['IN_PROGRESS', '2026-10-24T08:01:00Z']]),
            $this->deployment('before-window', 'production', '2026-10-21T08:00:00Z', [['SUCCESS', '2026-10-21T08:05:00Z']]),
        ], true, 'never-read')]);
        $result = $this->collect(['environments' => ['production']]);
        $deployments = array_values(array_filter($result->events, fn (array $event): bool => $event['origin'] === 'github_deployment'));
        $this->assertSame(['tagged', 'replaced'], array_column($deployments, 'external_id'));
        $this->assertSame('Production · v2.0.0', $deployments[0]['title']);
        $this->assertSame('production · '.substr(str_repeat('a1', 20), 0, 7), $deployments[1]['title']);
        $this->assertSame('2026-10-24T08:04:00+00:00', $deployments[1]['occurred_at']);
        $this->assertSame('https://github.com/example/repo/commit/'.str_repeat('a1', 20), $deployments[1]['url']);
        $this->assertSame('2026-10-23T00:00:00+00:00', $result->authoritativeEvents['github_deployment']);
        Http::assertSent(fn (Request $request): bool => str_contains($request['query'], 'RepositoryDeployments') && $request['variables']['environments'] === ['production']);
        Http::assertNotSent(fn (Request $request): bool => ($request['variables']['after'] ?? null) === 'never-read');
    }

    public function test_without_environments_no_deployments_are_read(): void
    {
        $this->githubFake();
        $this->collect();
        Http::assertNotSent(fn (Request $request): bool => str_contains($request['query'] ?? '', 'deployments('));
    }

    public function test_missing_deployment_permission_is_reported_without_provider_details(): void
    {
        $this->githubFake(override: function (Request $request) {
            if (str_contains($request['query'], 'RepositoryDeployments')) {
                return ['errors' => [['type' => 'FORBIDDEN', 'message' => 'Resource not accessible by integration synthetic-provider-secret']], 'data' => ['repository' => ['deployments' => null]]];
            }

            return null;
        });
        try {
            $this->collect(['environments' => ['production']]);
            $this->fail('A forbidden deployment read was accepted.');
        } catch (GitHubDeploymentAccess $exception) {
            $this->assertStringNotContainsString('synthetic-provider-secret', $exception->getMessage());
        }
    }

    public function test_github_app_requests_deployment_access_only_when_environments_are_chosen(): void
    {
        $this->githubFake(override: function (Request $request) {
            if (str_contains($request->url(), '/access_tokens')) {
                $this->assertSame(['contents' => 'read', 'issues' => 'read', 'pull_requests' => 'read', 'deployments' => 'read'], $request['permissions']);

                return Http::response(['message' => 'The permissions requested are not granted to this installation.'], 422);
            }

            return null;
        });
        $this->expectException(GitHubDeploymentAccess::class);
        $this->collect(['environments' => ['production']], ['client_id' => 'Iv23_synthetic', 'installation_id' => '123', 'private_key' => self::signingKeys()['private']]);
    }

    public function test_environment_discovery_lists_recent_deployment_environments_once(): void
    {
        $this->githubFake(override: function (Request $request) {
            $this->assertStringContainsString('DeploymentEnvironments', $request['query']);

            return ['data' => ['repository' => ['deployments' => $this->page([
                ['id' => 'd1', 'environment' => 'staging'], ['id' => 'd2', 'environment' => 'production'],
                ['id' => 'd3', 'environment' => 'Production'], ['id' => 'd4', 'environment' => 'preview-12'],
            ], true, 'never-read')]]];
        });
        $names = (new GitHubConnector($this->http()))->environments('example/repo', ['token' => 'synthetic-token'], $this->now());
        $this->assertSame(['preview-12', 'production', 'staging'], $names);
        Http::assertSentCount(1);
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
        return [['partial'], ['noRepository'], ['wrongRepository'], ['negativeCount'], ['missingCount'], ['badTimestamp'], ['badOrdering'], ['unsafeReleaseUrl'], ['futureRelease'], ['badReleaseOrdering']];
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
            case 'badReleaseOrdering': $releases = ['' => $this->page([$this->release('older', created: '2026-10-20T00:00:00Z'), $this->release('newer', created: '2026-10-21T00:00:00Z')])];
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

    private function githubFake(array $commits = [], array $pulls = [], array $releases = [], array $info = [], ?callable $override = null, array $deployments = []): void
    {
        $info += ['nameWithOwner' => 'example/repo', 'createdAt' => '2026-01-01T00:00:00Z', 'isEmpty' => false,
            'hasIssuesEnabled' => true, 'issues' => ['totalCount' => 7], 'defaultBranchRef' => ['target' => ['oid' => 'default-branch-snapshot']]];
        $commits = $commits ?: ['' => $this->page([])];
        $pulls = $pulls ?: ['' => $this->page([])];
        $releases = $releases ?: ['' => $this->page([])];
        $deployments = $deployments ?: ['' => $this->page([])];
        Http::fake(function (Request $request) use ($commits, $pulls, $releases, $info, $override, $deployments) {
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
            } elseif (str_contains($request['query'], 'RepositoryDeployments')) {
                $data = ['deployments' => $deployments[$cursor]];
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

    private function release(string $id = 'release-one', string $published = '2026-10-24T12:00:00Z', string $created = '2026-10-24T11:00:00Z'): array
    {
        return ['id' => $id, 'name' => 'Release '.$id, 'tagName' => 'v1.0', 'description' => 'Synthetic release notes',
            'url' => 'https://github.com/example/repo/releases/tag/'.$id, 'createdAt' => $created, 'publishedAt' => $published, 'isDraft' => false, 'isPrerelease' => false];
    }

    /** @param list<array{string, string}> $statuses state and time pairs */
    private function deployment(string $id, string $environment, string $created, array $statuses, ?array $ref = ['name' => 'main', 'prefix' => 'refs/heads/']): array
    {
        return ['id' => $id, 'commitOid' => str_repeat('a1', 20), 'createdAt' => $created, 'environment' => $environment, 'ref' => $ref,
            'statuses' => ['nodes' => array_map(fn (array $status): array => ['state' => $status[0], 'createdAt' => $status[1]], $statuses)]];
    }
}
