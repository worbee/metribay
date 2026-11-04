<?php

namespace Metribay\Core\Services;

use Metribay\Core\Models\Source;

/** Resource identity is independent of the connected account, history window and event filters. */
final class ProjectResources
{
    public static function field(string $kind): string
    {
        return match ($kind) {
            'umami' => 'website_id', 'ga4' => 'property_id', 'gsc', 'bing' => 'site_url',
            'github', 'github_releases', 'azure_devops' => 'repository', 'uptimerobot' => 'monitor_id', 'wporg' => 'slug', 'pagespeed' => 'url',
            default => throw new \InvalidArgumentException('Unknown provider.'),
        };
    }

    public static function key(string $kind, string $id): string
    {
        return in_array($kind, ['github', 'github_releases', 'umami', 'azure_devops'], true) ? strtolower($id) : $id;
    }

    /** @return list<string> */
    public static function added(int $projectId, string $kind): array
    {
        $field = self::field($kind);

        return Source::query()->where('project_id', $projectId)->where('kind', $kind)->get()
            ->map(fn (Source $source): string => self::key($kind, (string) ($source->config[$field] ?? '')))->all();
    }

    public static function contains(int $projectId, string $kind, string $id): bool
    {
        return in_array(self::key($kind, $id), self::added($projectId, $kind), true);
    }
}
