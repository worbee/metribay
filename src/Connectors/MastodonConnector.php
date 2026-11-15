<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

use Carbon\CarbonImmutable;

/**
 * Current follower count of a public Mastodon account, read from its own server's public API without an account.
 * Mastodon publishes only the current count, so each collection records one UTC snapshot.
 */
final class MastodonConnector extends AbstractConnector
{
    public function collect(SourceConfiguration $source, array $credentials, CarbonImmutable $now): CollectionResult
    {
        $this->utcOnly($source, 'Mastodon');
        try {
            [$username, $server] = SocialAccount::mastodonParts($this->text($source->config['account'] ?? null, 'Mastodon account', 320));
        } catch (ConnectorException) {
            throw new ConnectorException('Use a Mastodon account such as name@mastodon.social, or its profile link.');
        }
        $response = $this->http->request('GET', 'https://'.$server.'/api/v1/accounts/lookup', ['query' => ['acct' => $username], 'headers' => ['Accept' => 'application/json']]);
        if ($response->status() === 429) {
            throw new ProviderRequestLimit;
        }
        if ($response->status() === 404 || $response->status() === 410) {
            throw new ConnectorException('This Mastodon server could not find the account. Check the username and server.');
        }
        if ($response->status() === 401 || $response->status() === 403) {
            throw new ConnectorException('This Mastodon server does not allow public account lookups.');
        }
        $account = $this->json($response, 'Mastodon');
        $returned = strtolower($this->text($account['username'] ?? null, 'Mastodon username', 320));
        $acct = strtolower($this->text($account['acct'] ?? null, 'Mastodon account address', 640));
        // A local account's address is its username. Anything else is a copy of an account on another server.
        if ($returned !== $username || ($acct !== $username && $acct !== $username.'@'.$server)) {
            throw new ConnectorException('Mastodon returned a different account. Enter the account with the server it lives on.');
        }
        if (! array_key_exists('followers_count', $account)) {
            throw new ConnectorException('Mastodon did not report a follower count for this account.');
        }
        $today = $now->utc()->startOfDay()->toDateString();

        return new CollectionResult(daily: [$this->daily('followers', $today, (float) $this->count($account['followers_count'], 'Mastodon'))],
            replaceDailyDates: [$today => ['followers']]);
    }
}
