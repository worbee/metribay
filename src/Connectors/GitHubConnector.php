<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;

/** Full-window activity recomputation handles late merges and force pushes. */
final class GitHubConnector extends AbstractConnector implements ListsDeploymentEnvironments
{
    public const API_VERSION = '2026-03-10';

    private const REPOSITORY = <<<'GRAPHQL'
query RepositoryMetrics($owner: String!, $name: String!) {
  repository(owner: $owner, name: $name) {
    nameWithOwner createdAt isEmpty hasIssuesEnabled
    issues(first: 1, states: OPEN) { totalCount }
    defaultBranchRef { name target { oid } }
  }
}
GRAPHQL;

    private const PULLS = <<<'GRAPHQL'
query MergedPulls($owner: String!, $name: String!, $after: String) {
  repository(owner: $owner, name: $name) {
    pullRequests(first: 100, after: $after, states: MERGED,
                 orderBy: {field: UPDATED_AT, direction: DESC}) {
      nodes { id mergedAt updatedAt }
      pageInfo { hasNextPage endCursor }
    }
  }
}
GRAPHQL;

    private const COMMITS = <<<'GRAPHQL'
query CommitActivity($owner: String!, $name: String!, $oid: GitObjectID!,
                     $since: GitTimestamp!, $until: GitTimestamp!, $after: String) {
  repository(owner: $owner, name: $name) {
    object(oid: $oid) {
      ... on Commit {
        history(first: 100, after: $after, since: $since, until: $until) {
          nodes { oid committedDate additions deletions parents(first: 2) { totalCount } }
          pageInfo { hasNextPage endCursor }
        }
      }
    }
  }
}
GRAPHQL;

    private const RELEASES = <<<'GRAPHQL'
query RepositoryReleases($owner: String!, $name: String!, $after: String) {
  repository(owner: $owner, name: $name) {
    releases(first: 100, after: $after, orderBy: {field: CREATED_AT, direction: DESC}) {
      nodes { id name tagName description url createdAt publishedAt isDraft isPrerelease }
      pageInfo { hasNextPage endCursor }
    }
  }
}
GRAPHQL;

    private const DEPLOYMENTS = <<<'GRAPHQL'
query RepositoryDeployments($owner: String!, $name: String!, $environments: [String!], $after: String) {
  repository(owner: $owner, name: $name) {
    deployments(first: 100, after: $after, environments: $environments, orderBy: {field: CREATED_AT, direction: DESC}) {
      nodes { id commitOid createdAt environment ref { name prefix } statuses(first: 50) { nodes { state createdAt logUrl } } }
      pageInfo { hasNextPage endCursor }
    }
  }
}
GRAPHQL;

    private const DEPLOYMENT_ENVIRONMENTS = <<<'GRAPHQL'
query DeploymentEnvironments($owner: String!, $name: String!, $after: String) {
  repository(owner: $owner, name: $name) {
    deployments(first: 100, after: $after, orderBy: {field: CREATED_AT, direction: DESC}) {
      nodes { id environment }
      pageInfo { hasNextPage endCursor }
    }
  }
}
GRAPHQL;

    /** Releases are listed by creation date; read this far past the window for drafts published later. */
    private const RELEASE_DRAFT_MARGIN_DAYS = 30;

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $this->utcOnly($source, 'GitHub');
        $repository = $this->repository($source->config['repository'] ?? null);
        $environments = $source->config['environments'] ?? [];
        if (! is_array($environments) || ! array_is_list($environments) || count($environments) > 10) {
            throw new ConnectorException('GitHub deployment environments must be a short list of names.');
        }
        foreach ($environments as $environment) {
            $this->text($environment, 'GitHub deployment environment', 255);
        }
        // GitHub deliberately rereads the full configured window; old commits can become reachable.
        $configuration = new SourceConfiguration($source->kind, $source->config, $source->reportingTimezone);
        [$start, $today] = $this->window($configuration, $now, 1, 181);
        $maximumPages = $source->config['max_pages'] ?? 1000;
        if (! is_int($maximumPages) || $maximumPages < 1 || $maximumPages > 1000) {
            throw new ConnectorException('GitHub max_pages must be between 1 and 1000.');
        }
        $now = $now->utc();
        [$owner, $name] = explode('/', $repository);
        $variables = ['owner' => $owner, 'name' => $name];
        $graphql = $this->client($repository, $credentials, $now, $environments !== []);
        $info = $graphql(self::REPOSITORY, $variables);
        if (strtolower($this->text($info['nameWithOwner'] ?? null, 'GitHub repository identity')) !== strtolower($repository)
            || ! is_bool($info['isEmpty'] ?? null) || ! is_bool($info['hasIssuesEnabled'] ?? null)) {
            throw new ConnectorException('GitHub returned inconsistent repository metadata.');
        }
        $created = $this->timestamp($info['createdAt'] ?? null, 'GitHub');
        if ($created > $now) {
            throw new ConnectorException('GitHub returned a future repository creation date.');
        }
        $issues = $this->count($this->object($info['issues'] ?? null, 'GitHub')['totalCount'] ?? null, 'GitHub');
        if ($info['isEmpty']) {
            $start = $start->max($created->startOfDay());
        }
        $values = [];
        for ($day = $start; $day <= $today; $day = $day->addDay()) {
            $values[$day->toDateString()] = ['prs_merged' => 0.0, 'commits' => 0.0, 'loc_changed' => 0.0];
        }
        $previousUpdated = null;
        foreach ($this->pages($graphql, self::PULLS, $variables, 'pullRequests', $maximumPages) as $nodes) {
            $lastUpdated = null;
            foreach ($nodes as $row) {
                $merged = $this->timestamp($row['mergedAt'] ?? null, 'GitHub');
                $updated = $this->timestamp($row['updatedAt'] ?? null, 'GitHub');
                if ($merged > $updated || ($previousUpdated !== null && $updated > $previousUpdated)) {
                    throw new ConnectorException('GitHub returned inconsistent merged-PR ordering.');
                }
                $previousUpdated = $lastUpdated = $updated;
                if ($merged >= $start && $merged <= $now) {
                    $values[$merged->toDateString()]['prs_merged']++;
                }
            }
            if ($lastUpdated !== null && $lastUpdated < $start) {
                break;
            }
        }
        if (! $info['isEmpty']) {
            $branch = $this->object($info['defaultBranchRef'] ?? null, 'GitHub');
            $target = $this->object($branch['target'] ?? null, 'GitHub');
            $oid = $this->text($target['oid'] ?? null, 'GitHub default-branch commit');
            $commitVariables = $variables + ['oid' => $oid, 'since' => $start->toIso8601String(), 'until' => $now->toIso8601String()];
            foreach ($this->pages($graphql, self::COMMITS, $commitVariables, 'history', $maximumPages) as $nodes) {
                foreach ($nodes as $row) {
                    $committed = $this->timestamp($row['committedDate'] ?? null, 'GitHub');
                    $additions = $this->count($row['additions'] ?? null, 'GitHub');
                    $deletions = $this->count($row['deletions'] ?? null, 'GitHub');
                    $parents = $this->count($this->object($row['parents'] ?? null, 'GitHub')['totalCount'] ?? null, 'GitHub');
                    if ($committed >= $start && $committed <= $now) {
                        $values[$committed->toDateString()]['commits']++;
                        if ($parents <= 1) {
                            $values[$committed->toDateString()]['loc_changed'] += $additions + $deletions;
                        }
                    }
                }
            }
        }
        $events = $this->releases($graphql, $variables, $repository, $start, $now, $maximumPages);
        if ($environments !== []) {
            $defaultBranch = is_array($info['defaultBranchRef'] ?? null) ? $this->text($info['defaultBranchRef']['name'] ?? null, 'GitHub default branch', 255) : null;
            $events = array_merge($events, $this->deployments($graphql, $variables + ['environments' => $environments], $repository, $defaultBranch, $start, $now, $maximumPages));
        }
        $daily = [];
        foreach ($values as $date => $metrics) {
            foreach ($metrics as $metric => $value) {
                $daily[] = $this->daily($metric, $date, $value, $date === $today->toDateString());
            }
        }
        if ($info['hasIssuesEnabled']) {
            $daily[] = $this->daily('open_issues', $today->toDateString(), (float) $issues);
        }

        // Only the read window is reconciled. Without environments, no deployment events belong to this source.
        return new CollectionResult(daily: $daily, events: $events, authoritativeEvents: ['github_release' => $start->toIso8601String(),
            'github_deployment' => $environments === [] ? null : $start->toIso8601String()],
            replaceDailyDates: [$today->toDateString() => ['open_issues']]);
    }

    /**
     * Environment names of the repository's most recent deployments, for choosing which deployments become events.
     *
     * @param  array<string, mixed>  $credentials
     * @return list<string>
     */
    public function environments(string $repository, array $credentials, CarbonImmutable $now): array
    {
        $repository = $this->repository($repository);
        [$owner, $name] = explode('/', $repository);
        $graphql = $this->client($repository, $credentials, $now->utc(), true);
        $names = [];
        foreach ($this->pages($graphql, self::DEPLOYMENT_ENVIRONMENTS, ['owner' => $owner, 'name' => $name], 'deployments', 1) as $nodes) {
            foreach ($nodes as $row) {
                $environment = $this->text($row['environment'] ?? null, 'GitHub deployment environment', 255);
                $names[mb_strtolower($environment)] ??= $environment;
            }
            // The latest hundred deployments are enough to name the environments in use.
            break;
        }
        $names = array_values($names);
        natcasesort($names);

        return array_values($names);
    }

    private function repository(mixed $value): string
    {
        $repository = $this->text($value, 'GitHub repository');
        if (! preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D', $repository)
            || array_intersect(explode('/', $repository), ['.', '..']) !== []) {
            throw new ConnectorException('Use a GitHub repository in owner/repository format.');
        }

        return $repository;
    }

    /**
     * GraphQL client for one repository. Installation tokens are limited to that repository and read access.
     *
     * @param  array<string, mixed>  $credentials
     * @return callable(string, array<string, mixed>): array<string, mixed>
     */
    private function client(string $repository, array $credentials, CarbonImmutable $now, bool $deployments): callable
    {
        $name = explode('/', $repository)[1];
        $token = isset($credentials['token']) ? $this->text($credentials['token'], 'GitHub token') : null;
        $tokenExpires = $token !== null ? $now->addDay() : null;
        $installation = null;
        $clientId = null;
        $privateKey = null;
        if ($token === null) {
            $installation = $this->text((string) ($credentials['installation_id'] ?? ''), 'GitHub installation ID');
            $clientId = $this->text($credentials['client_id'] ?? $credentials['app_id'] ?? null, 'GitHub App client ID');
            $privateKey = $credentials['private_key'] ?? null;
            if (! preg_match('/^[1-9][0-9]*$/D', $installation) || ! preg_match('/^[A-Za-z0-9_]+$/D', $clientId)
                || ! is_string($privateKey) || strlen($privateKey) > 16384) {
                throw new ConnectorException('Use valid GitHub App identifiers and an in-memory PEM private key.');
            }
        }
        $headers = fn (string $bearer): array => ['Authorization' => 'Bearer '.$bearer, 'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => self::API_VERSION];
        $permissions = ['contents' => 'read', 'issues' => 'read', 'pull_requests' => 'read'] + ($deployments ? ['deployments' => 'read'] : []);
        $accessToken = function () use (&$token, &$tokenExpires, $now, $installation, $clientId, $privateKey, $name, $headers, $permissions, $deployments): string {
            if ($token !== null && $tokenExpires !== null && $tokenExpires > $now->addMinute()) {
                return $token;
            }
            try {
                $assertion = JWT::encode(['iss' => $clientId, 'iat' => $now->getTimestamp() - 60,
                    'exp' => $now->getTimestamp() + 540], $privateKey, 'RS256');
            } catch (\Throwable) {
                throw new ConnectorException('GitHub App private-key signing failed.');
            }
            $response = $this->http->request('POST', 'https://api.github.com/app/installations/'.$installation.'/access_tokens', [
                'headers' => $headers($assertion), 'json' => ['repositories' => [$name], 'permissions' => $permissions],
            ]);
            if ($deployments && $response->status() === 422) {
                // GitHub rejects permissions that the installation was not granted.
                throw new GitHubDeploymentAccess;
            }
            $data = $this->json($response, 'GitHub installation authentication', 201);
            $token = $this->text($data['token'] ?? null, 'GitHub installation token');
            $tokenExpires = $this->timestamp($data['expires_at'] ?? null, 'GitHub');
            if ($tokenExpires <= $now->addMinute()) {
                throw new ConnectorException('GitHub returned an expired installation token.');
            }

            return $token;
        };

        return function (string $query, array $arguments) use (&$token, &$tokenExpires, $accessToken, $headers, $installation): array {
            $send = fn () => $this->http->request('POST', 'https://api.github.com/graphql', [
                'headers' => $headers($accessToken()), 'json' => ['query' => $query, 'variables' => $arguments],
            ]);
            $response = $send();
            if ($response->status() === 401 && $installation !== null) {
                $token = $tokenExpires = null;
                $response = $send();
            }
            $data = $this->json($response, 'GitHub');
            if (! empty($data['errors'])) {
                if (in_array($query, [self::DEPLOYMENTS, self::DEPLOYMENT_ENVIRONMENTS], true) && $this->forbidden($data['errors'])) {
                    throw new GitHubDeploymentAccess;
                }
                throw new ConnectorException('GitHub returned GraphQL errors or partial data. Check permissions and rate limits.');
            }
            $root = $this->object($data['data'] ?? null, 'GitHub');

            return $this->object($root['repository'] ?? null, 'GitHub');
        };
    }

    /** True when every GraphQL error says the token may not read the requested data. */
    private function forbidden(mixed $errors): bool
    {
        if (! is_array($errors) || ! array_is_list($errors)) {
            return false;
        }
        foreach ($errors as $error) {
            if (! is_array($error) || (($error['type'] ?? null) !== 'FORBIDDEN'
                && ! str_starts_with((string) ($error['message'] ?? ''), 'Resource not accessible'))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Published releases inside the window. Releases are listed newest-created first, so reading stops once
     * creation dates fall a margin before the window; a draft created earlier and published later is still found.
     *
     * @param  array<string, mixed>  $variables
     * @return list<array{origin: string, external_id: string, title: string, url: string, occurred_at: string, body: string}>
     */
    private function releases(callable $graphql, array $variables, string $repository, CarbonImmutable $start, CarbonImmutable $now, int $maximumPages): array
    {
        $events = [];
        $previousCreated = null;
        foreach ($this->pages($graphql, self::RELEASES, $variables, 'releases', $maximumPages) as $nodes) {
            $lastCreated = null;
            foreach ($nodes as $row) {
                if (! is_bool($row['isDraft'] ?? null) || ! is_bool($row['isPrerelease'] ?? null)) {
                    throw new ConnectorException('GitHub returned invalid release metadata.');
                }
                $created = $this->timestamp($row['createdAt'] ?? null, 'GitHub');
                if ($previousCreated !== null && $created > $previousCreated) {
                    throw new ConnectorException('GitHub returned inconsistent release ordering.');
                }
                $previousCreated = $lastCreated = $created;
                if ($row['isDraft']) {
                    continue;
                }
                $published = $this->timestamp($row['publishedAt'] ?? null, 'GitHub');
                if ($published > $now) {
                    throw new ConnectorException('GitHub returned a future release publication date.');
                }
                if ($published < $start) {
                    continue;
                }
                $tag = $this->text($row['tagName'] ?? null, 'GitHub release tag');
                $title = ($row['name'] ?? null) ?: $tag;
                $body = $row['description'] ?? '';
                if (! is_string($title) || ! is_string($body)) {
                    throw new ConnectorException('GitHub returned invalid release text.');
                }
                $url = $this->text($row['url'] ?? null, 'GitHub release URL');
                $parts = parse_url($url);
                if ($parts === false || ($parts['scheme'] ?? null) !== 'https' || ($parts['host'] ?? null) !== 'github.com'
                    || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
                    || ! str_starts_with(strtolower($parts['path'] ?? ''), '/'.strtolower($repository).'/releases/')) {
                    throw new ConnectorException('GitHub returned an invalid release URL.');
                }
                $events[] = ['origin' => 'github_release', 'external_id' => $this->text($row['id'] ?? null, 'GitHub release identity', 255),
                    'title' => mb_substr($title.($row['isPrerelease'] ? ' (prerelease)' : ''), 0, 200),
                    'body' => mb_substr($body, 0, 4000), 'url' => $url, 'occurred_at' => $published->toIso8601String()];
            }
            if ($lastCreated !== null && $lastCreated < $start->subDays(self::RELEASE_DRAFT_MARGIN_DAYS)) {
                break;
            }
        }

        return $events;
    }

    /**
     * Successful deployments to the chosen environments inside the window, from the default branch or a tag.
     * GitHub Actions records one deployment per job that names an environment, and another set per re-run,
     * so deployments from the same workflow run become one event. A run counts when a deployment succeeded
     * after its last failed one; it is dated by its last success and links to the run. Deployments outside
     * Actions are one event each, linking to the commit. Deployments from other or deleted branches, such as
     * jobs that only use the environment's secrets on a pull request, are skipped.
     *
     * @param  array<string, mixed>  $variables
     * @return list<array{origin: string, external_id: string, title: string, url: string, occurred_at: string, body: string}>
     */
    private function deployments(callable $graphql, array $variables, string $repository, ?string $defaultBranch, CarbonImmutable $start, CarbonImmutable $now, int $maximumPages): array
    {
        $wanted = array_map(mb_strtolower(...), $variables['environments']);
        $runPrefix = 'https://github.com/'.strtolower($repository).'/actions/runs/';
        $groups = [];
        $previousCreated = null;
        foreach ($this->pages($graphql, self::DEPLOYMENTS, $variables, 'deployments', $maximumPages) as $nodes) {
            $lastCreated = null;
            foreach ($nodes as $row) {
                $created = $this->timestamp($row['createdAt'] ?? null, 'GitHub');
                if ($previousCreated !== null && $created > $previousCreated) {
                    throw new ConnectorException('GitHub returned inconsistent deployment ordering.');
                }
                $previousCreated = $lastCreated = $created;
                $environment = $this->text($row['environment'] ?? null, 'GitHub deployment environment', 255);
                $ref = $row['ref'] ?? null;
                $prefix = is_array($ref) ? ($ref['prefix'] ?? null) : null;
                $refName = is_array($ref) ? $this->text($ref['name'] ?? null, 'GitHub deployment ref', 255) : null;
                if (! in_array(mb_strtolower($environment), $wanted, true)
                    || ! ($prefix === 'refs/tags/' || ($prefix === 'refs/heads/' && $defaultBranch !== null && $refName === $defaultBranch))) {
                    continue;
                }
                $commit = $this->text($row['commitOid'] ?? null, 'GitHub deployment commit');
                if (! preg_match('/^[0-9a-f]{40}$/D', $commit)) {
                    throw new ConnectorException('GitHub returned an invalid deployment commit.');
                }
                $succeeded = $failed = null;
                $run = null;
                foreach ($this->list($this->object($row['statuses'] ?? null, 'GitHub')['nodes'] ?? null, 'GitHub') as $status) {
                    $status = $this->object($status, 'GitHub');
                    $at = $this->timestamp($status['createdAt'] ?? null, 'GitHub');
                    if (! is_string($status['state'] ?? null)) {
                        throw new ConnectorException('GitHub returned an invalid deployment status.');
                    }
                    if ($status['state'] === 'SUCCESS') {
                        $succeeded = $succeeded === null ? $at : min($succeeded, $at);
                    } elseif (in_array($status['state'], ['FAILURE', 'ERROR'], true)) {
                        $failed = $failed === null ? $at : max($failed, $at);
                    }
                    $log = $status['logUrl'] ?? null;
                    if (is_string($log) && str_starts_with(strtolower($log), $runPrefix) && preg_match('~^[0-9]{1,20}(?=/|$)~', substr($log, strlen($runPrefix)), $match)) {
                        $run = $match[0];
                    }
                }
                if ($succeeded !== null && $succeeded > $now) {
                    throw new ConnectorException('GitHub returned a future deployment date.');
                }
                // One run can deploy to several chosen environments; each environment gets its own event.
                $key = $run !== null ? 'run:'.$run.':'.mb_strtolower($environment) : 'deployment:'.$this->text($row['id'] ?? null, 'GitHub deployment identity', 255);
                $group = $groups[$key] ?? ['environment' => $environment, 'commit' => $commit, 'tag' => $prefix === 'refs/tags/' ? $refName : null,
                    'run' => $run, 'created' => $created, 'succeeded' => null, 'failed' => null, 'id' => $row['id']];
                // Pages are newest first: keep the newest deployment's commit and tag, and the run's latest outcomes.
                if ($succeeded !== null && ($group['succeeded'] === null || $succeeded > $group['succeeded'])) {
                    $group['succeeded'] = $succeeded;
                }
                if ($failed !== null && ($group['failed'] === null || $failed > $group['failed'])) {
                    $group['failed'] = $failed;
                }
                $groups[$key] = $group;
            }
            // A deployment usually succeeds within minutes of its creation; a day covers slow ones and long runs.
            if ($lastCreated !== null && $lastCreated < $start->subDay()) {
                break;
            }
        }
        $events = [];
        foreach ($groups as $key => $group) {
            if ($group['succeeded'] === null || $group['succeeded'] < $start
                || ($group['failed'] !== null && $group['failed'] > $group['succeeded'])) {
                continue;
            }
            $events[] = ['origin' => 'github_deployment', 'external_id' => $group['run'] !== null ? $key : $this->text($group['id'], 'GitHub deployment identity', 255),
                'title' => mb_substr($group['environment'].' · '.($group['tag'] ?? substr($group['commit'], 0, 7)), 0, 200), 'body' => '',
                'url' => $group['run'] !== null ? 'https://github.com/'.$repository.'/actions/runs/'.$group['run'] : 'https://github.com/'.$repository.'/commit/'.$group['commit'],
                'occurred_at' => $group['succeeded']->toIso8601String()];
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return \Generator<int, list<array<string, mixed>>>
     */
    private function pages(callable $graphql, string $query, array $variables, string $field, int $maximum): \Generator
    {
        $cursor = null;
        $seenCursors = $seenNodes = [];
        for ($pageNumber = 0; $pageNumber < $maximum; $pageNumber++) {
            $repository = $graphql($query, $variables + ['after' => $cursor]);
            $container = $field === 'history' ? $this->object($repository['object'] ?? null, 'GitHub') : $repository;
            $connection = $this->object($container[$field] ?? null, 'GitHub');
            $nodes = $this->list($connection['nodes'] ?? null, 'GitHub');
            $page = $this->object($connection['pageInfo'] ?? null, 'GitHub');
            if (count($nodes) > 100 || ! is_bool($page['hasNextPage'] ?? null)) {
                throw new ConnectorException('GitHub returned an invalid paginated connection.');
            }
            $validated = [];
            foreach ($nodes as $raw) {
                $node = $this->object($raw, 'GitHub');
                $identity = $this->text($node[$field === 'history' ? 'oid' : 'id'] ?? null, 'GitHub object identity');
                if (isset($seenNodes[$identity])) {
                    throw new ConnectorException('GitHub pagination returned duplicate objects. Retry collection.');
                }
                $seenNodes[$identity] = true;
                $validated[] = $node;
            }
            $next = $page['endCursor'] ?? null;
            if ($page['hasNextPage']) {
                if ($nodes === [] || ! is_string($next) || $next === '' || isset($seenCursors[$next])) {
                    throw new ConnectorException('GitHub pagination did not advance.');
                }
                $seenCursors[$next] = true;
            }
            yield $validated;
            if (! $page['hasNextPage']) {
                return;
            }
            $cursor = $next;
        }
        throw new ConnectorException('GitHub pagination limit reached. No partial totals were accepted.');
    }
}
