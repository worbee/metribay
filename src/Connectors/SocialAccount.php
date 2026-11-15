<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

/**
 * Normalizes the public social accounts that follower sources track. Identities are lowercase,
 * because Bluesky handles and Mastodon usernames are both matched without regard to case.
 */
final class SocialAccount
{
    private const HOST = '(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?';

    /** A Bluesky handle (example.bsky.social) or DID, from a handle, @handle or bsky.app profile link. */
    public static function bluesky(string $input): string
    {
        $value = strtolower(trim($input));
        if (preg_match('~^https://(?:www\.)?bsky\.app/profile/([^/?#]+)/?$~D', $value, $match)) {
            $value = rawurldecode($match[1]);
        }
        $value = ltrim($value, '@');
        if (preg_match('/^did:plc:[a-z2-7]{24}$/D', $value) || preg_match('/^did:web:'.self::HOST.'$/D', $value)) {
            return $value;
        }
        if (! preg_match('/^'.self::HOST.'$/D', $value) || str_ends_with($value, '.local') || str_ends_with($value, '.localhost')) {
            throw new ConnectorException('Use a Bluesky handle such as name.bsky.social, or its profile link.');
        }

        return $value;
    }

    /** A Mastodon account as username@server, from @username@server, username@server or a profile link. */
    public static function mastodon(string $input): string
    {
        $value = strtolower(trim($input));
        if (preg_match('~^https://([^/?#@:]+)/@([^/?#@]+)/?$~D', $value, $match)) {
            $value = $match[2].'@'.$match[1];
        }
        $value = ltrim($value, '@');
        if (! preg_match('/^([a-z0-9_](?:[a-z0-9_.-]{0,62}[a-z0-9_])?)@('.self::HOST.')$/D', $value, $parts)
            || str_ends_with($parts[2], '.local') || str_ends_with($parts[2], '.localhost') || str_ends_with($parts[2], '.internal')) {
            throw new ConnectorException('Use a Mastodon account such as name@mastodon.social, or its profile link.');
        }

        return $value;
    }

    /** @return array{0: string, 1: string} Username and server of a normalized Mastodon account. */
    public static function mastodonParts(string $account): array
    {
        [$username, $server] = explode('@', self::mastodon($account), 2);

        return [$username, $server];
    }
}
