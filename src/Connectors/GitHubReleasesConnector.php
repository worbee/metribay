<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;

/**
 * Current total of GitHub release asset downloads. GitHub publishes a lifetime count per asset,
 * not a daily history, so each collection records one UTC snapshot. Drafts are skipped.
 * Uploaded assets are counted; GitHub's automatic source archives are not in that total.
 */
final class GitHubReleasesConnector extends AbstractConnector
{
    private const PER_PAGE = 100;

    private const MAX_PAGES = 20;

    private const MAX_TOTAL = 9_007_199_254_740_991;

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $this->utcOnly($source, 'GitHub');
        $repository = $this->repository($source->config['repository'] ?? null);
        $token = isset($credentials['token']) ? $this->text($credentials['token'], 'GitHub token', 2048) : null;
        $headers = ['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => GitHubConnector::API_VERSION];
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer '.$token;
        }
        $info = $this->object($this->payload($this->http->request('GET', 'https://api.github.com/repos/'.$repository, ['headers' => $headers]), 'GitHub repository'), 'GitHub');
        $name = $this->text($info['full_name'] ?? null, 'GitHub repository identity');
        if (strtolower($name) !== strtolower($repository)) {
            throw new ConnectorException('GitHub returned a different repository.');
        }
        $total = 0;
        $seenReleases = [];
        $seenAssets = [];
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $rows = $this->list($this->payload($this->http->request('GET', 'https://api.github.com/repos/'.$repository.'/releases', [
                'headers' => $headers, 'query' => ['per_page' => self::PER_PAGE, 'page' => $page],
            ]), 'GitHub releases'), 'GitHub');
            if (count($rows) > self::PER_PAGE) {
                throw new ConnectorException('GitHub returned an invalid release page.');
            }
            foreach ($rows as $raw) {
                $release = $this->object($raw, 'GitHub');
                if (! is_bool($release['draft'] ?? null)) {
                    throw new ConnectorException('GitHub returned invalid release metadata.');
                }
                $id = $this->identity($release['id'] ?? null, 'GitHub release identity');
                if (isset($seenReleases[$id])) {
                    throw new ConnectorException('GitHub pagination returned duplicate releases. Retry collection.');
                }
                $seenReleases[$id] = true;
                if ($release['draft']) {
                    continue;
                }
                foreach ($this->list($release['assets'] ?? null, 'GitHub') as $asset) {
                    $asset = $this->object($asset, 'GitHub');
                    $assetId = $this->identity($asset['id'] ?? null, 'GitHub release asset identity');
                    if (isset($seenAssets[$assetId])) {
                        throw new ConnectorException('GitHub returned a duplicate release asset.');
                    }
                    $seenAssets[$assetId] = true;
                    $downloads = $this->count($asset['download_count'] ?? null, 'GitHub');
                    if ($total > self::MAX_TOTAL - $downloads) {
                        throw new ConnectorException('GitHub returned a download total that is too large.');
                    }
                    $total += $downloads;
                }
            }
            if (count($rows) < self::PER_PAGE) {
                $today = $now->utc()->startOfDay()->toDateString();

                return new CollectionResult(daily: [$this->daily('release_downloads', $today, (float) $total)],
                    replaceDailyDates: [$today => ['release_downloads']]);
            }
        }

        throw new ConnectorException('GitHub pagination limit reached. No partial totals were accepted.');
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

    private function identity(mixed $value, string $field): string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        $identity = $this->text($value, $field, 40);
        if (! preg_match('/^[1-9][0-9]*$/D', $identity)) {
            throw new ConnectorException('A valid '.$field.' is required.');
        }

        return $identity;
    }

    private function payload(Response $response, string $label): mixed
    {
        if ($response->status() === 429 || ($response->status() === 403 && $this->throttled($response))) {
            throw new ProviderRequestLimit;
        }
        if ($response->status() === 404) {
            throw new ConnectorException('GitHub could not find this repository. Check owner/repository, or add a token for a private repository.');
        }
        if ($response->status() === 401) {
            throw new ConnectorException('GitHub denied this token. Check that it can read the repository.');
        }
        if ($response->status() !== 200) {
            throw new ConnectorException($label.' returned HTTP '.$response->status().'. Check access and rate limits.');
        }
        try {
            return json_decode($response->body(), true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new ConnectorException($label.' returned invalid JSON.');
        }
    }

    /** Primary and secondary GitHub rate limits, without reading the response body into an error. */
    private function throttled(Response $response): bool
    {
        return $response->header('X-RateLimit-Remaining') === '0' || $response->header('Retry-After') !== '';
    }
}
