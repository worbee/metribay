<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

/**
 * Current follower count of a public Bluesky account, read from the public AppView without an account.
 * Bluesky publishes only the current count, so each collection records one UTC snapshot.
 */
final class BlueskyConnector extends AbstractConnector
{
    public const ENDPOINT = 'https://public.api.bsky.app/xrpc/app.bsky.actor.getProfile';

    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $this->utcOnly($source, 'Bluesky');
        try {
            $actor = SocialAccount::bluesky($this->text($source->config['account'] ?? null, 'Bluesky account', 300));
        } catch (ConnectorException) {
            throw new ConnectorException('Use a Bluesky handle such as name.bsky.social, or its profile link.');
        }
        $response = $this->http->request('GET', self::ENDPOINT, ['query' => ['actor' => $actor], 'headers' => ['Accept' => 'application/json']]);
        if ($response->status() === 429) {
            throw new ProviderRequestLimit;
        }
        if ($response->status() === 400 || $response->status() === 404) {
            throw new ConnectorException('Bluesky could not find this account. Check the handle.');
        }
        $profile = $this->json($response, 'Bluesky');
        $did = $this->text($profile['did'] ?? null, 'Bluesky account identity', 2048);
        $handle = strtolower($this->text($profile['handle'] ?? null, 'Bluesky handle', 253));
        if ($actor !== strtolower($did) && $actor !== $handle) {
            throw new ConnectorException('Bluesky returned a different account.');
        }
        if (! array_key_exists('followersCount', $profile)) {
            throw new ConnectorException('Bluesky did not report a follower count for this account.');
        }
        $today = $now->utc()->startOfDay()->toDateString();

        return new CollectionResult(daily: [$this->daily('followers', $today, (float) $this->count($profile['followersCount'], 'Bluesky'))],
            replaceDailyDates: [$today => ['followers']]);
    }
}
