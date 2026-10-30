<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;

/** Full-window activity recomputation handles late merges and force pushes. */
final class GitHubConnector extends AbstractConnector
{
    public const API_VERSION = '2026-03-10';

    private const REPOSITORY = <<<'GRAPHQL'
query RepositoryMetrics($owner: String!, $name: String!) {
  repository(owner: $owner, name: $name) {
    nameWithOwner createdAt isEmpty hasIssuesEnabled
    issues(first: 1, states: OPEN) { totalCount }
    defaultBranchRef { target { oid } }
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
      nodes { id name tagName description url publishedAt isDraft isPrerelease }
      pageInfo { hasNextPage endCursor }
    }
  }
}
GRAPHQL;

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $this->utcOnly($source, 'GitHub');
        $repository = $this->text($source->config['repository'] ?? null, 'GitHub repository');
        if (! preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D', $repository)
            || array_intersect(explode('/', $repository), ['.', '..']) !== []) {
            throw new ConnectorException('Use a GitHub repository in owner/repository format.');
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
        $accessToken = function () use (&$token, &$tokenExpires, $now, $installation, $clientId, $privateKey, $name, $headers): string {
            if ($token !== null && $tokenExpires !== null && $tokenExpires > $now->addMinute()) {
                return $token;
            }
            try {
                $assertion = JWT::encode(['iss' => $clientId, 'iat' => $now->getTimestamp() - 60,
                    'exp' => $now->getTimestamp() + 540], $privateKey, 'RS256');
            } catch (\Throwable) {
                throw new ConnectorException('GitHub App private-key signing failed.');
            }
            $data = $this->json($this->http->request('POST', 'https://api.github.com/app/installations/'.$installation.'/access_tokens', [
                'headers' => $headers($assertion), 'json' => ['repositories' => [$name], 'permissions' => [
                    'contents' => 'read', 'issues' => 'read', 'pull_requests' => 'read',
                ]],
            ]), 'GitHub installation authentication', 201);
            $token = $this->text($data['token'] ?? null, 'GitHub installation token');
            $tokenExpires = $this->timestamp($data['expires_at'] ?? null, 'GitHub');
            if ($tokenExpires <= $now->addMinute()) {
                throw new ConnectorException('GitHub returned an expired installation token.');
            }

            return $token;
        };
        $graphql = function (string $query, array $arguments) use (&$token, &$tokenExpires, $accessToken, $headers, $installation): array {
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
                throw new ConnectorException('GitHub returned GraphQL errors or partial data. Check permissions and rate limits.');
            }
            $root = $this->object($data['data'] ?? null, 'GitHub');

            return $this->object($root['repository'] ?? null, 'GitHub');
        };
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
        $events = [];
        // Creation order is not publication order: read all releases before reconciliation.
        foreach ($this->pages($graphql, self::RELEASES, $variables, 'releases', $maximumPages) as $nodes) {
            foreach ($nodes as $row) {
                if (! is_bool($row['isDraft'] ?? null) || ! is_bool($row['isPrerelease'] ?? null)) {
                    throw new ConnectorException('GitHub returned invalid release metadata.');
                }
                if ($row['isDraft']) {
                    continue;
                }
                $published = $this->timestamp($row['publishedAt'] ?? null, 'GitHub');
                if ($published > $now) {
                    throw new ConnectorException('GitHub returned a future release publication date.');
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
                $events[] = ['external_id' => $this->text($row['id'] ?? null, 'GitHub release identity', 255),
                    'title' => mb_substr($title.($row['isPrerelease'] ? ' (prerelease)' : ''), 0, 200),
                    'body' => mb_substr($body, 0, 4000), 'url' => $url, 'occurred_at' => $published->toIso8601String()];
            }
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

        return new CollectionResult(daily: $daily, events: $events, authoritativeEvents: true,
            replaceDailyDates: [$today->toDateString() => ['open_issues']]);
    }

    /**
     * @param  array<string, string>  $variables
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
