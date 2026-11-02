<?php

declare(strict_types=1);

namespace Metribay\Core\Tests\Connectors;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Connectors\AzureDevOpsConnector;
use Metribay\Core\Connectors\AzureDevOpsDeploymentAccess;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Connectors\ConnectorException;
use Metribay\Core\Connectors\ResourceDiscovery;
use Metribay\Core\Connectors\SourceConfiguration;
use Metribay\Core\Services\ProviderCatalog;

require_once __DIR__.'/FixtureTestCase.php';

final class AzureDevOpsConnectorTest extends FixtureTestCase
{
    private const REPOSITORY_ID = '3411ebc1-d5aa-464f-9615-0b527bc66719';

    private const COMMIT = 'ae875f124fe5c625664fae12af26cc294ed1575f';

    public function test_activity_counts_default_branch_commits_completed_prs_and_default_branch_pipeline_runs(): void
    {
        $this->azureFake(builds: [
            $this->build(1, 'succeeded', '2026-10-24T08:00:00.1234567Z'),
            $this->build(2, 'partiallySucceeded', '2026-10-24T09:00:00Z'),
            $this->build(3, 'failed', '2026-10-25T09:00:00Z'),
            $this->build(4, 'canceled', '2026-10-25T09:30:00Z'),
            $this->build(5, 'failed', '2026-10-25T09:40:00Z', 'refs/pull/7/merge'),
        ]);
        $result = $this->collect();
        $this->assertSame(['commits' => 2.0, 'pipeline_runs_failed' => 0.0, 'pipeline_runs_succeeded' => 2.0, 'prs_merged' => 1.0], $this->valuesOn($result->daily, '2026-10-24'));
        $this->assertSame(['commits' => 1.0, 'open_issues' => 2.0, 'pipeline_runs_failed' => 1.0, 'pipeline_runs_succeeded' => 0.0, 'prs_merged' => 0.0], $this->valuesOn($result->daily, '2026-10-25'));
        $this->assertSame(['commits' => 0.0, 'pipeline_runs_failed' => 0.0, 'pipeline_runs_succeeded' => 0.0, 'prs_merged' => 0.0], $this->valuesOn($result->daily, '2026-10-23'));
        foreach ($result->daily as $row) {
            $this->assertSame($row['metric'] !== 'open_issues' && $row['date'] === '2026-10-25', $row['is_partial']);
        }
        $this->assertSame([], $result->events);
        $this->assertSame(['azure_devops_deployment' => null], $result->authoritativeEvents);
        $this->assertSame(['2026-10-25' => ['open_issues']], $result->replaceDailyDates);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://dev.azure.com/fabrikam/')
            && $request->header('Authorization')[0] === 'Basic '.base64_encode(':synthetic-pat'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/commits?')
            && $this->sentQuery($request)['searchCriteria.itemVersion.version'] === 'main' && $this->sentQuery($request)['searchCriteria.$top'] === '1000');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/pullrequests?')
            && $this->sentQuery($request)['searchCriteria.status'] === 'completed' && $this->sentQuery($request)['searchCriteria.queryTimeRangeType'] === 'closed');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/_apis/wit/wiql')
            && str_contains($request['query'], "([System.WorkItemType] = 'Bug' AND [System.State] IN ('New', 'Active', 'Resolved'))"));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'distributedtask'));
    }

    public function test_project_and_repository_names_are_encoded_into_paths(): void
    {
        $this->azureFake(project: 'Fabrikam Fiber', repository: 'Web App');
        $this->collect(repository: 'Fabrikam Fiber/Web App');
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://dev.azure.com/fabrikam/Fabrikam%20Fiber/_apis/git/repositories/Web%20App?'));
    }

    public function test_paging_reads_until_a_short_page_and_rejects_duplicates(): void
    {
        $page = array_map(fn (int $n): array => ['commitId' => sprintf('%040x', $n), 'committer' => ['date' => '2026-10-24T10:00:00Z']], range(1, 1000));
        $this->azureFake(commits: [$page, [['commitId' => 'tail', 'committer' => ['date' => '2026-10-25T08:00:00Z']]]]);
        $result = $this->collect();
        $this->assertSame(1000.0, $this->valuesOn($result->daily, '2026-10-24')['commits']);
        $this->assertSame(1.0, $this->valuesOn($result->daily, '2026-10-25')['commits']);

        $this->azureFake(commits: [$page, [$page[0]]]);
        $this->expectExceptionMessage('duplicate objects');
        $this->collect();
    }

    public function test_an_empty_repository_has_known_zero_activity_without_reading_commits(): void
    {
        $this->azureFake(defaultBranch: null, builds: []);
        $result = $this->collect();
        $this->assertSame(0.0, $this->valuesOn($result->daily, '2026-10-24')['commits']);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/commits'));
    }

    public function test_successful_deployments_of_default_branch_and_tag_runs_become_one_event_per_run_and_environment(): void
    {
        $this->azureFake(builds: [
            $this->build(10, 'succeeded', '2026-10-24T12:00:00Z'),
            $this->build(11, 'succeeded', '2026-10-24T13:00:00Z', 'refs/tags/v1.2.0'),
            $this->build(12, 'failed', '2026-10-24T14:00:00Z'),
            $this->build(13, 'succeeded', '2026-10-24T15:00:00Z', 'refs/heads/feature'),
        ], environments: [['id' => 4, 'name' => 'Production'], ['id' => 5, 'name' => 'Staging']], records: [
            4 => [
                // Run 12 succeeded once, then a retried stage failed: it does not count.
                $this->record(12, 'failed', '2026-10-24T13:59:00Z'), $this->record(12, 'succeeded', '2026-10-24T13:40:00Z'),
                // Run 10 deployed through two jobs, one of them retried after a failure.
                $this->record(10, 'succeeded', '2026-10-24T11:58:00Z'), $this->record(10, 'succeeded', '2026-10-24T11:50:00Z'), $this->record(10, 'failed', '2026-10-24T11:40:00Z'),
                $this->record(11, 'succeededWithIssues', '2026-10-24T12:55:00Z'),
                $this->record(13, 'succeeded', '2026-10-24T14:55:00Z'),
                $this->record(9, 'succeeded', '2026-10-24T09:00:00Z'),
                $this->record(8, 'running', null),
            ],
            5 => [$this->record(10, 'succeeded', '2026-10-24T11:30:00Z')],
        ]);
        $result = $this->collect(environments: ['production']);
        $this->assertSame([
            ['origin' => 'azure_devops_deployment', 'external_id' => 'run:10:production', 'title' => 'Production · ae875f1', 'body' => '',
                'url' => 'https://dev.azure.com/fabrikam/Fabrikam/_build/results?buildId=10', 'occurred_at' => '2026-10-24T11:58:00+00:00'],
            ['origin' => 'azure_devops_deployment', 'external_id' => 'run:11:production', 'title' => 'Production · v1.2.0', 'body' => '',
                'url' => 'https://dev.azure.com/fabrikam/Fabrikam/_build/results?buildId=11', 'occurred_at' => '2026-10-24T12:55:00+00:00'],
        ], $result->events);
        $this->assertSame(['azure_devops_deployment' => '2026-10-23T00:00:00+00:00'], $result->authoritativeEvents);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/environments/5/'));
    }

    public function test_deployment_records_stop_once_they_are_older_than_the_window(): void
    {
        $this->azureFake(builds: [$this->build(10, 'succeeded', '2026-10-24T12:00:00Z')], environments: [['id' => 4, 'name' => 'production']],
            records: [4 => [$this->record(10, 'succeeded', '2026-10-24T11:58:00Z'), $this->record(2, 'succeeded', '2026-09-01T00:00:00Z')]], recordContinuation: 'more');
        $this->assertCount(1, $this->collect(environments: ['production'])->events);
        Http::assertSentCount(9);
    }

    public function test_missing_environment_scope_is_reported_separately(): void
    {
        $this->azureFake(environmentStatus: 401);
        $this->expectException(AzureDevOpsDeploymentAccess::class);
        $this->collect(environments: ['production']);
    }

    public function test_environment_names_are_listed_for_suggestions(): void
    {
        $this->azureFake(environments: [['id' => 4, 'name' => 'staging'], ['id' => 5, 'name' => 'Production'], ['id' => 6, 'name' => 'production']]);
        $names = (new AzureDevOpsConnector($this->http()))->environments('Fabrikam/Fabrikam', $this->credentials(), $this->now());
        $this->assertSame(['Production', 'staging'], $names);
    }

    public function test_rejected_tokens_and_throttling_fail_without_partial_metrics(): void
    {
        foreach ([203 => 'did not accept the personal access token', 401 => 'did not accept the personal access token', 404 => 'could not find', 429 => 'limiting requests'] as $status => $message) {
            $this->azureFake(repositoryStatus: $status);
            try {
                $this->collect();
                $this->fail('Collection should fail for HTTP '.$status);
            } catch (ConnectorException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    public function test_repository_identity_must_match(): void
    {
        $this->azureFake(project: 'Other');
        $this->expectExceptionMessage('inconsistent repository metadata');
        $this->collect();
    }

    public function test_basic_process_issues_are_counted_and_oversized_results_are_unknown(): void
    {
        $this->azureFake(categories: [['referenceName' => 'Microsoft.RequirementCategory', 'workItemTypes' => [['name' => 'Issue']]]],
            states: ['Issue' => [['name' => 'To Do', 'category' => 'Proposed'], ['name' => 'Doing', 'category' => 'InProgress'], ['name' => 'Done', 'category' => 'Completed']]]);
        $this->assertSame(2.0, $this->valuesOn($this->collect()->daily, '2026-10-25')['open_issues']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/wiql')
            && str_contains($request['query'], "([System.WorkItemType] = 'Issue' AND [System.State] IN ('To Do', 'Doing'))"));

        $this->azureFake(openWorkItems: 20000);
        $this->assertArrayNotHasKey('open_issues', $this->valuesOn($this->collect()->daily, '2026-10-25'));
    }

    public function test_organization_accepts_names_and_azure_devops_addresses_only(): void
    {
        foreach (['fabrikam', ' fabrikam ', 'https://dev.azure.com/fabrikam', 'dev.azure.com/fabrikam/Project/_git/repo', 'https://fabrikam.visualstudio.com/'] as $input) {
            $this->assertSame('fabrikam', AzureDevOpsConnector::organization($input));
        }
        foreach (['', '-fabrikam', 'fabrikam-', 'evil.example/fabrikam', 'https://evil.example', 'fab rikam', str_repeat('a', 51)] as $input) {
            try {
                AzureDevOpsConnector::organization($input);
                $this->fail('Accepted '.$input);
            } catch (ConnectorException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_catalog_validates_connections_and_project_repository_sources(): void
    {
        $this->assertSame(['organization' => 'fabrikam', 'token' => 'secret'], ProviderCatalog::credentials('azure_devops', ['organization' => 'https://dev.azure.com/fabrikam', 'token' => ' secret ']));
        $this->assertSame(['repository' => 'Fabrikam Fiber/Web App', 'days' => 180, 'environments' => ['production']],
            ProviderCatalog::source('azure_devops', ['repository' => ' Fabrikam Fiber / Web App ', 'environments' => ['production']], 'UTC'));
        $this->assertSame(['commits', 'prs_merged', 'pipeline_runs_succeeded', 'pipeline_runs_failed', 'open_issues'], ProviderCatalog::metricKeys('azure_devops'));
        foreach (['repo', 'a/b/c', 'a\\b/c', '/repo'] as $repository) {
            try {
                ProviderCatalog::source('azure_devops', ['repository' => $repository], 'UTC');
                $this->fail('Accepted '.$repository);
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(ValidationException::class);
        ProviderCatalog::credentials('azure_devops', ['organization' => 'evil.example', 'token' => 'secret']);
    }

    public function test_discovery_lists_enabled_repositories_across_projects(): void
    {
        Http::fake(['https://dev.azure.com/fabrikam/_apis/git/repositories*' => Http::response(['count' => 3, 'value' => [
            ['id' => self::REPOSITORY_ID, 'name' => 'Web', 'project' => ['name' => 'Shop'], 'isDisabled' => false],
            ['id' => 'b', 'name' => 'Old', 'project' => ['name' => 'Shop'], 'isDisabled' => true],
            ['id' => 'c', 'name' => 'api', 'project' => ['name' => 'Backend']],
        ]])]);
        $this->assertSame([
            ['id' => 'Backend/api', 'name' => 'Backend/api', 'domain' => '', 'timezone' => 'UTC'],
            ['id' => 'Shop/Web', 'name' => 'Shop/Web', 'domain' => '', 'timezone' => 'UTC'],
        ], (new ResourceDiscovery($this->http()))->discover('azure_devops', $this->credentials()));
    }

    /**
     * Query parameters as sent, keeping the dots that PHP's parse_str would turn into underscores.
     *
     * @return array<string, string>
     */
    private function sentQuery(Request $request): array
    {
        $query = [];
        foreach (explode('&', (string) parse_url($request->url(), PHP_URL_QUERY)) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $query[urldecode($key)] = urldecode($value);
        }

        return $query;
    }

    /** @param list<string> $environments */
    private function collect(string $repository = 'Fabrikam/Fabrikam', array $environments = []): CollectionResult
    {
        $config = ['repository' => $repository, 'days' => 3] + ($environments !== [] ? ['environments' => $environments] : []);

        return (new AzureDevOpsConnector($this->http()))->collect(new SourceConfiguration('azure_devops', $config), $this->credentials(), $this->now());
    }

    /** @return array<string, string> */
    private function credentials(): array
    {
        return ['organization' => 'fabrikam', 'token' => 'synthetic-pat'];
    }

    /** @return array<string, mixed> */
    private function build(int $id, string $result, string $finished, string $branch = 'refs/heads/main'): array
    {
        return ['id' => $id, 'result' => $result, 'status' => 'completed', 'finishTime' => $finished, 'sourceBranch' => $branch, 'sourceVersion' => self::COMMIT];
    }

    /** @return array<string, mixed> */
    private function record(int $run, string $result, ?string $finished): array
    {
        return ['id' => random_int(1, PHP_INT_MAX), 'owner' => ['id' => $run, 'name' => '20261024.'.$run], 'result' => $result, 'stageName' => 'Deploy', 'jobName' => 'Deploy']
            + ($finished !== null ? ['finishTime' => $finished] : []);
    }

    /**
     * @param  list<list<array<string, mixed>>>|null  $commits  Pages of commits.
     * @param  list<array<string, mixed>>|null  $builds
     * @param  list<array{id: int, name: string}>  $environments
     * @param  array<int, list<array<string, mixed>>>  $records
     * @param  list<array<string, mixed>>|null  $categories
     * @param  array<string, list<array{name: string, category: string}>>|null  $states
     */
    private function azureFake(string $project = 'Fabrikam', string $repository = 'Fabrikam', ?string $defaultBranch = 'refs/heads/main', ?array $commits = null,
        ?array $builds = null, array $environments = [], array $records = [], ?string $recordContinuation = null, int $repositoryStatus = 200,
        int $environmentStatus = 200, ?array $categories = null, ?array $states = null, int $openWorkItems = 2): void
    {
        $commits ??= [[
            ['commitId' => 'c1', 'committer' => ['date' => '2026-10-24T08:00:00Z']],
            ['commitId' => 'c2', 'committer' => ['date' => '2026-10-24T23:59:59Z']],
            ['commitId' => 'c3', 'committer' => ['date' => '2026-10-25T09:00:00Z']],
        ]];
        $builds ??= [$this->build(1, 'succeeded', '2026-10-24T08:00:00Z')];
        $categories ??= [['referenceName' => 'Microsoft.BugCategory', 'workItemTypes' => [['name' => 'Bug']]],
            ['referenceName' => 'Microsoft.RequirementCategory', 'workItemTypes' => [['name' => 'User Story']]]];
        $states ??= ['Bug' => [['name' => 'New', 'category' => 'Proposed'], ['name' => 'Active', 'category' => 'InProgress'],
            ['name' => 'Resolved', 'category' => 'Resolved'], ['name' => 'Closed', 'category' => 'Completed']]];
        Http::swap(new Factory(app('events')));
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($project, $repository, $defaultBranch, $commits, $builds, $environments, $records, $recordContinuation, $repositoryStatus, $environmentStatus, $categories, $states, $openWorkItems) {
            $path = rawurldecode((string) parse_url($request->url(), PHP_URL_PATH));
            $list = fn (array $rows, array $headers = []) => Http::response(['count' => count($rows), 'value' => $rows], 200, $headers);
            if (str_contains($path, '/distributedtask/environments')) {
                if ($environmentStatus !== 200) {
                    return Http::response('', $environmentStatus);
                }
                if (preg_match('~/environments/(\d+)/environmentdeploymentrecords$~', $path, $match)) {
                    return $list($records[(int) $match[1]] ?? [], $recordContinuation !== null ? ['x-ms-continuationtoken' => $recordContinuation] : []);
                }

                return $list($environments);
            }

            return match (true) {
                str_ends_with($path, '/git/repositories/'.$repository) => $repositoryStatus === 200
                    ? Http::response(['id' => self::REPOSITORY_ID, 'name' => $repository, 'project' => ['name' => $project], 'isDisabled' => false]
                        + ($defaultBranch !== null ? ['defaultBranch' => $defaultBranch] : []))
                    : Http::response('<html>Sign in</html>', $repositoryStatus),
                str_ends_with($path, '/commits') => $list($commits[intdiv((int) $this->sentQuery($request)['searchCriteria.$skip'], 1000)] ?? []),
                str_ends_with($path, '/pullrequests') => $list([['pullRequestId' => 20, 'status' => 'completed', 'closedDate' => '2026-10-24T16:30:02.0413914Z']]),
                str_ends_with($path, '/build/builds') => $list($builds),
                str_ends_with($path, '/wit/workitemtypecategories') => $list($categories),
                (bool) preg_match('~/wit/workitemtypes/([^/]+)/states$~', $path, $match) => $list($states[$match[1]] ?? []),
                str_ends_with($path, '/wit/wiql') => Http::response(['queryType' => 'flat', 'workItems' => array_map(fn (int $id): array => ['id' => $id], $openWorkItems > 0 ? range(1, $openWorkItems) : [])]),
                default => throw new \RuntimeException('Unexpected request '.$request->url()),
            };
        });
    }
}
