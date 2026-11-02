<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

/**
 * Azure DevOps Services through an organization-scoped personal access token. One data source is one Git
 * repository in a project. Like GitHub, the full window is reread on every sync.
 */
final class AzureDevOpsConnector extends AbstractConnector implements ListsDeploymentEnvironments
{
    public const API_VERSION = '7.1';

    private const PAGE_SIZE = 1000;

    /** WIQL returns at most this many work items; a larger result is unknown, not a count. */
    private const WIQL_LIMIT = 20000;

    /** Builds counted as succeeded or failed. Canceled and unfinished runs are not counted. */
    private const SUCCEEDED = ['succeeded', 'partiallySucceeded'];

    /**
     * Normalizes an organization name, also accepting dev.azure.com and visualstudio.com addresses.
     * Only a name is kept, so the access token is never sent to another host.
     */
    public static function organization(mixed $value): string
    {
        if (! is_string($value)) {
            throw new ConnectorException('A valid Azure DevOps organization is required.');
        }
        $value = trim($value);
        if (preg_match('~^(?:https://)?dev\.azure\.com/([^/?#]+)(?:[/?#].*)?$~iD', $value, $match)) {
            $value = $match[1];
        } elseif (preg_match('~^(?:https://)?([^./?#]+)\.visualstudio\.com(?:[/?#].*)?$~iD', $value, $match)) {
            $value = $match[1];
        }
        if (! preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,48}[A-Za-z0-9])?$/D', $value)) {
            throw new ConnectorException('A valid Azure DevOps organization is required.');
        }

        return $value;
    }

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $this->utcOnly($source, 'Azure DevOps');
        [$project, $name] = $this->repository($source->config['repository'] ?? null);
        $environments = $source->config['environments'] ?? [];
        if (! is_array($environments) || ! array_is_list($environments) || count($environments) > 10) {
            throw new ConnectorException('Azure DevOps deployment environments must be a short list of names.');
        }
        foreach ($environments as $environment) {
            $this->text($environment, 'Azure DevOps deployment environment', 255);
        }
        $maximumPages = $source->config['max_pages'] ?? 50;
        if (! is_int($maximumPages) || $maximumPages < 1 || $maximumPages > 50) {
            throw new ConnectorException('Azure DevOps max_pages must be between 1 and 50.');
        }
        // History is always reread across the configured window, so late merges and force pushes are reflected.
        [$start, $today] = $this->window(new SourceConfiguration($source->kind, $source->config, $source->reportingTimezone), $now, 1, 181);
        $now = $now->utc();
        $organization = self::organization($credentials['organization'] ?? null);
        $api = $this->client($organization, $credentials);
        [$repository] = $api('GET', $this->path($project, 'git', 'repositories', $name));
        $repositoryId = $this->text($repository['id'] ?? null, 'Azure DevOps repository identity', 64);
        $repositoryProject = $this->object($repository['project'] ?? null, 'Azure DevOps');
        if (mb_strtolower($this->text($repository['name'] ?? null, 'Azure DevOps repository name', 255)) !== mb_strtolower($name)
            || mb_strtolower($this->text($repositoryProject['name'] ?? null, 'Azure DevOps project name', 255)) !== mb_strtolower($project)
            || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $repositoryId)) {
            throw new ConnectorException('Azure DevOps returned inconsistent repository metadata.');
        }
        if (($repository['isDisabled'] ?? false) === true) {
            throw new ConnectorException('This Azure DevOps repository is disabled.');
        }
        // An empty repository has no default branch yet; it has no commits and no branch builds.
        $defaultBranch = $repository['defaultBranch'] ?? null;
        if ($defaultBranch !== null && (! is_string($defaultBranch) || ! str_starts_with($defaultBranch, 'refs/heads/') || strlen($defaultBranch) <= 11)) {
            throw new ConnectorException('Azure DevOps returned an invalid default branch.');
        }
        $values = [];
        for ($day = $start; $day <= $today; $day = $day->addDay()) {
            $values[$day->toDateString()] = ['commits' => 0.0, 'prs_merged' => 0.0, 'pipeline_runs_succeeded' => 0.0, 'pipeline_runs_failed' => 0.0];
        }
        $count = function (string $metric, CarbonImmutable $at) use (&$values, $start, $now): void {
            if ($at >= $start && $at <= $now) {
                $values[$at->toDateString()][$metric]++;
            }
        };
        $repositoryPath = $this->path($project, 'git', 'repositories', $repositoryId);
        if ($defaultBranch !== null) {
            $query = ['searchCriteria.itemVersion.version' => substr($defaultBranch, 11), 'searchCriteria.itemVersion.versionType' => 'branch',
                'searchCriteria.fromDate' => $start->toIso8601ZuluString(), 'searchCriteria.toDate' => $now->toIso8601ZuluString()];
            foreach ($this->skipPages($api, $repositoryPath.'/commits', $query, 'searchCriteria.', 'commitId', $maximumPages) as $commit) {
                $count('commits', $this->timestamp($this->object($commit['committer'] ?? null, 'Azure DevOps')['date'] ?? null, 'Azure DevOps'));
            }
        }
        $query = ['searchCriteria.status' => 'completed', 'searchCriteria.queryTimeRangeType' => 'closed',
            'searchCriteria.minTime' => $start->toIso8601ZuluString(), 'searchCriteria.maxTime' => $now->toIso8601ZuluString()];
        foreach ($this->skipPages($api, $repositoryPath.'/pullrequests', $query, '', 'pullRequestId', $maximumPages) as $pull) {
            if (($pull['status'] ?? null) !== 'completed') {
                throw new ConnectorException('Azure DevOps returned a pull request that is not completed.');
            }
            $count('prs_merged', $this->timestamp($pull['closedDate'] ?? null, 'Azure DevOps'));
        }
        $runs = [];
        $query = ['repositoryId' => $repositoryId, 'repositoryType' => 'TfsGit', 'statusFilter' => 'completed',
            'queryOrder' => 'finishTimeDescending', 'minTime' => $start->toIso8601ZuluString()];
        foreach ($this->tokenPages($api, $this->path($project, 'build', 'builds'), $query, $maximumPages) as $build) {
            $id = $this->count($build['id'] ?? null, 'Azure DevOps');
            $branch = $this->text($build['sourceBranch'] ?? null, 'Azure DevOps build branch', 500);
            $finished = $this->timestamp($build['finishTime'] ?? null, 'Azure DevOps');
            if (! is_string($build['result'] ?? null)) {
                throw new ConnectorException('Azure DevOps returned an invalid build result.');
            }
            // Pull request validation and feature branches would blur the default branch's health.
            if ($branch === $defaultBranch) {
                if (in_array($build['result'], self::SUCCEEDED, true)) {
                    $count('pipeline_runs_succeeded', $finished);
                } elseif ($build['result'] === 'failed') {
                    $count('pipeline_runs_failed', $finished);
                }
            }
            if ($branch === $defaultBranch || str_starts_with($branch, 'refs/tags/')) {
                $commit = $build['sourceVersion'] ?? null;
                $runs[$id] = ['tag' => str_starts_with($branch, 'refs/tags/') ? substr($branch, 10) : null,
                    'commit' => is_string($commit) && preg_match('/^[0-9a-f]{40}$/D', $commit) ? $commit : null];
            }
        }
        $events = $environments === [] ? [] : $this->deployments($api, $organization, $project, $environments, $runs, $start, $now, $maximumPages);
        $daily = [];
        foreach ($values as $date => $metrics) {
            foreach ($metrics as $metric => $value) {
                $daily[] = $this->daily($metric, $date, $value, $date === $today->toDateString());
            }
        }
        $open = $this->openWorkItems($api, $project);
        if ($open !== null) {
            $daily[] = $this->daily('open_issues', $today->toDateString(), (float) $open);
        }

        // Without environments, no deployment events belong to this source.
        return new CollectionResult(daily: $daily, events: $events,
            authoritativeEvents: ['azure_devops_deployment' => $environments === [] ? null : $start->toIso8601String()],
            replaceDailyDates: [$today->toDateString() => ['open_issues']]);
    }

    /**
     * Names of the project's pipeline environments, for choosing which deployments become events.
     *
     * @param  array<string, mixed>  $credentials
     * @return list<string>
     */
    public function environments(string $repository, array $credentials, CarbonImmutable $now): array
    {
        [$project] = $this->repository($repository);
        $api = $this->client(self::organization($credentials['organization'] ?? null), $credentials);
        $names = [];
        foreach ($this->environmentList($api, $project) as $environment) {
            $names[mb_strtolower($environment['name'])] ??= $environment['name'];
        }
        $names = array_values($names);
        natcasesort($names);

        return array_values($names);
    }

    /** @return array{string, string} Project and repository names. */
    private function repository(mixed $value): array
    {
        $repository = $this->text($value, 'Azure DevOps repository', 129);
        $parts = explode('/', $repository);
        if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '' || str_contains($repository, '\\')) {
            throw new ConnectorException('Use an Azure DevOps repository in project/repository format.');
        }

        return [trim($parts[0]), trim($parts[1])];
    }

    private function path(string ...$segments): string
    {
        return implode('/', [rawurlencode($segments[0]), '_apis', ...array_map(rawurlencode(...), array_slice($segments, 1))]);
    }

    /**
     * Request function for one organization. Returns the decoded body and the continuation token, if any.
     *
     * @param  array<string, mixed>  $credentials
     * @return callable(string, string, array<string, mixed>=, ?array<string, mixed>=): array{array<string, mixed>, ?string}
     */
    private function client(string $organization, array $credentials): callable
    {
        $token = $this->text($credentials['token'] ?? null, 'Azure DevOps personal access token', 1024);
        $headers = ['Authorization' => 'Basic '.base64_encode(':'.$token), 'X-TFS-FedAuthRedirect' => 'Suppress'];

        return function (string $method, string $path, array $query = [], ?array $json = null) use ($organization, $headers): array {
            $options = ['headers' => $headers, 'query' => $query + ['api-version' => self::API_VERSION]];
            if ($json !== null) {
                $options['json'] = $json;
            }
            $response = $this->http->request($method, 'https://dev.azure.com/'.$organization.'/'.$path, $options);
            $this->refuse($response, str_contains($path, '/_apis/distributedtask/environments'));
            $continuation = $response->header('x-ms-continuationtoken');

            return [$this->json($response, 'Azure DevOps'), $continuation !== '' ? $continuation : null];
        };
    }

    /** Turns refusals into safe messages. Sign-in pages (HTTP 203) mean the token was not accepted. */
    private function refuse(Response $response, bool $environments): void
    {
        $status = $response->status();
        if ($status === 429) {
            throw new ConnectorException('Azure DevOps is limiting requests for this account. Collection will retry later.');
        }
        if ($environments && in_array($status, [401, 403], true)) {
            throw new AzureDevOpsDeploymentAccess;
        }
        if (in_array($status, [203, 401, 403], true)) {
            throw new ConnectorException('Azure DevOps did not accept the personal access token. Check that it is valid, not expired and created for this organization.');
        }
        if ($status === 404) {
            throw new ConnectorException('Azure DevOps could not find this project or repository.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function rows(array $data): array
    {
        return array_map(fn (mixed $row): array => $this->object($row, 'Azure DevOps'), $this->list($data['value'] ?? null, 'Azure DevOps'));
    }

    /**
     * Pages read with $top and $skip until a short page. Duplicate rows mean the list moved while reading.
     *
     * @param  array<string, mixed>  $query
     * @return \Generator<int, array<string, mixed>>
     */
    private function skipPages(callable $api, string $path, array $query, string $prefix, string $identity, int $maximum): \Generator
    {
        $seen = [];
        for ($page = 0; $page < $maximum; $page++) {
            $rows = $this->rows($api('GET', $path, $query + [$prefix.'$top' => self::PAGE_SIZE, $prefix.'$skip' => $page * self::PAGE_SIZE])[0]);
            if (count($rows) > self::PAGE_SIZE) {
                throw new ConnectorException('Azure DevOps returned an invalid page.');
            }
            foreach ($rows as $row) {
                $id = $this->text((string) ($row[$identity] ?? ''), 'Azure DevOps object identity', 64);
                if (isset($seen[$id])) {
                    throw new ConnectorException('Azure DevOps pagination returned duplicate objects. Retry collection.');
                }
                $seen[$id] = true;
                yield $row;
            }
            if (count($rows) < self::PAGE_SIZE) {
                return;
            }
        }
        throw new ConnectorException('Azure DevOps pagination limit reached. No partial totals were accepted.');
    }

    /**
     * Pages linked by the x-ms-continuationtoken header.
     *
     * @param  array<string, mixed>  $query
     * @return \Generator<int, array<string, mixed>>
     */
    private function tokenPages(callable $api, string $path, array $query, int $maximum, string $topName = '$top'): \Generator
    {
        $token = null;
        $seen = [];
        for ($page = 0; $page < $maximum; $page++) {
            [$data, $next] = $api('GET', $path, $query + [$topName => self::PAGE_SIZE] + ($token !== null ? ['continuationToken' => $token] : []));
            $rows = $this->rows($data);
            foreach ($rows as $row) {
                yield $row;
            }
            if ($next === null) {
                return;
            }
            if ($rows === [] || strlen($next) > 2048 || isset($seen[$next])) {
                throw new ConnectorException('Azure DevOps pagination did not advance.');
            }
            $seen[$next] = $token = $next;
        }
        throw new ConnectorException('Azure DevOps pagination limit reached. No partial totals were accepted.');
    }

    /** @return list<array{id: int, name: string}> */
    private function environmentList(callable $api, string $project): array
    {
        $environments = [];
        foreach ($this->tokenPages($api, $this->path($project, 'distributedtask', 'environments'), [], 10) as $row) {
            $environments[] = ['id' => $this->count($row['id'] ?? null, 'Azure DevOps'), 'name' => $this->text($row['name'] ?? null, 'Azure DevOps environment', 255)];
        }

        return $environments;
    }

    /**
     * Successful deployments of default-branch or tag runs of this repository to the chosen environments.
     * A run deploys through one or more jobs, and stages can be retried, so each run and environment becomes one
     * event. It counts when a job succeeded after the run's last failed one, is dated by its last success and
     * links to the run.
     *
     * @param  list<string>  $environments
     * @param  array<int, array{tag: ?string, commit: ?string}>  $runs  Runs of this repository finished inside the window.
     * @return list<array{origin: string, external_id: string, title: string, url: string, occurred_at: string, body: string}>
     */
    private function deployments(callable $api, string $organization, string $project, array $environments, array $runs, CarbonImmutable $start, CarbonImmutable $now, int $maximumPages): array
    {
        $wanted = array_map(mb_strtolower(...), $environments);
        $groups = [];
        foreach ($this->environmentList($api, $project) as $environment) {
            if (! in_array(mb_strtolower($environment['name']), $wanted, true)) {
                continue;
            }
            $path = $this->path($project, 'distributedtask', 'environments', (string) $environment['id'], 'environmentdeploymentrecords');
            $pages = $this->tokenPages($api, $path, [], $maximumPages, 'top');
            foreach ($pages as $record) {
                $owner = $this->object($record['owner'] ?? null, 'Azure DevOps');
                $run = $this->count($owner['id'] ?? null, 'Azure DevOps');
                $result = $record['result'] ?? null;
                $finished = isset($record['finishTime']) ? $this->timestamp($record['finishTime'], 'Azure DevOps') : null;
                // Records are listed newest first; a day before the window covers runs that deployed slowly.
                if ($finished !== null && $finished < $start->subDay()) {
                    break;
                }
                if (! isset($runs[$run]) || $finished === null || ! is_string($result)) {
                    continue;
                }
                if ($finished > $now) {
                    throw new ConnectorException('Azure DevOps returned a future deployment date.');
                }
                $key = 'run:'.$run.':'.mb_strtolower($environment['name']);
                $group = $groups[$key] ?? ['environment' => $environment['name'], 'run' => $run, 'succeeded' => null, 'failed' => null];
                if (in_array($result, ['succeeded', 'succeededWithIssues'], true)) {
                    $group['succeeded'] = $group['succeeded'] === null ? $finished : max($group['succeeded'], $finished);
                } elseif ($result === 'failed') {
                    $group['failed'] = $group['failed'] === null ? $finished : max($group['failed'], $finished);
                }
                $groups[$key] = $group;
            }
        }
        $events = [];
        foreach ($groups as $key => $group) {
            if ($group['succeeded'] === null || $group['succeeded'] < $start
                || ($group['failed'] !== null && $group['failed'] > $group['succeeded'])) {
                continue;
            }
            $run = $runs[$group['run']];
            $version = $run['tag'] ?? ($run['commit'] !== null ? substr($run['commit'], 0, 7) : '#'.$group['run']);
            $events[] = ['origin' => 'azure_devops_deployment', 'external_id' => $key,
                'title' => mb_substr($group['environment'].' · '.$version, 0, 200), 'body' => '',
                'url' => 'https://dev.azure.com/'.$organization.'/'.rawurlencode($project).'/_build/results?buildId='.$group['run'],
                'occurred_at' => $group['succeeded']->toIso8601String()];
        }

        return $events;
    }

    /**
     * Open work items of the bug types (the issue type in the Basic process, which has no bugs). Open means any state
     * outside the Completed and Removed categories, so customised processes count correctly.
     */
    private function openWorkItems(callable $api, string $project): ?int
    {
        $categories = [];
        foreach ($this->rows($api('GET', $this->path($project, 'wit', 'workitemtypecategories'))[0]) as $category) {
            $types = [];
            foreach ($this->list($category['workItemTypes'] ?? [], 'Azure DevOps') as $type) {
                $types[] = $this->text($this->object($type, 'Azure DevOps')['name'] ?? null, 'Azure DevOps work item type', 255);
            }
            $categories[(string) ($category['referenceName'] ?? '')] = $types;
        }
        $types = array_slice(($categories['Microsoft.BugCategory'] ?? []) ?: ($categories['Microsoft.RequirementCategory'] ?? []), 0, 10);
        $clauses = [];
        $quote = fn (string $value): string => "'".str_replace("'", "''", $value)."'";
        foreach ($types as $type) {
            $open = [];
            foreach ($this->rows($api('GET', $this->path($project, 'wit', 'workitemtypes', $type, 'states'))[0]) as $state) {
                $name = $this->text($state['name'] ?? null, 'Azure DevOps work item state', 255);
                if (! in_array($state['category'] ?? null, ['Completed', 'Removed'], true)) {
                    $open[] = $quote($name);
                }
            }
            if ($open !== []) {
                $clauses[] = '([System.WorkItemType] = '.$quote($type).' AND [System.State] IN ('.implode(', ', $open).'))';
            }
        }
        if ($clauses === []) {
            return $types === [] ? null : 0;
        }
        [$data] = $api('POST', $this->path($project, 'wit', 'wiql'), ['$top' => self::WIQL_LIMIT],
            ['query' => 'SELECT [System.Id] FROM WorkItems WHERE [System.TeamProject] = @project AND ('.implode(' OR ', $clauses).')']);
        $items = $this->list($data['workItems'] ?? null, 'Azure DevOps');

        return count($items) >= self::WIQL_LIMIT ? null : count($items);
    }
}
