<?php

namespace Metribay\Core\Services;

use Metribay\Core\Models\Source;

/** Resource identity is independent of the connected account, history window and event filters. */
final class ProjectResources
{
    public static function field(string $kind): string
    {
        return match ($kind) {
            'umami' => 'website_id', 'plausible', 'matomo' => 'site_id', 'ga4' => 'property_id', 'gsc', 'bing' => 'site_url',
            'github', 'github_releases', 'azure_devops' => 'repository', 'uptimerobot' => 'monitor_id', 'wporg' => 'slug', 'pagespeed', 'feed', 'sitemap' => 'url', 'paddle' => 'paddle_account', 'bluesky', 'mastodon' => 'account', 'posthog' => 'project_id',
            default => throw new \InvalidArgumentException('Unknown provider.'),
        };
    }

    public static function key(string $kind, string $id): string
    {
        return in_array($kind, ['github', 'github_releases', 'umami', 'azure_devops', 'plausible'], true) ? strtolower($id) : $id;
    }

    /**
     * The resource a source tracks. A PostHog project can have one usage source and one source per key event,
     * so the event is part of what it tracks.
     *
     * @param  array<string, mixed>  $config
     */
    public static function resource(string $kind, array $config): string
    {
        $id = (string) ($config[self::field($kind)] ?? '');

        return $kind === 'posthog' && isset($config['event']) ? $id."\n".$config['event'] : $id;
    }

    /** Whether a listed resource can be added only once; a PostHog project can still get key event sources. */
    public static function once(string $kind): bool
    {
        return $kind !== 'posthog';
    }

    /** @return list<string> */
    public static function added(int $projectId, string $kind): array
    {
        return Source::query()->where('project_id', $projectId)->where('kind', $kind)->get()
            ->map(fn (Source $source): string => self::key($kind, self::resource($kind, $source->config ?? [])))->all();
    }

    public static function contains(int $projectId, string $kind, string $id): bool
    {
        return in_array(self::key($kind, $id), self::added($projectId, $kind), true);
    }

    /**
     * The project's data sources that track a resource but are paused without the account they need, keyed by
     * resource, so setup can send people to choose an account instead of adding the resource again.
     *
     * @return array<string, int>
     */
    public static function pausedWithoutAccount(int $projectId, string $kind): array
    {
        if (! ProviderCatalog::requiresConnection($kind)) {
            return [];
        }

        return Source::query()->where('project_id', $projectId)->where('kind', $kind)->where('enabled', false)->whereNull('connection_id')->orderBy('id')->get()
            ->mapWithKeys(fn (Source $source): array => [self::key($kind, self::resource($kind, $source->config ?? [])) => (int) $source->id])->all();
    }
}
