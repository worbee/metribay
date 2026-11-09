<?php

namespace Metribay\Core\Metrics;

/**
 * Project page views. Overview and All metrics stay as they are. Presets and a custom mix
 * show a subset of the chart groups that exist on the project. The mix is carried in the URL;
 * saved names stay in the browser, one list per viewer, workspace and project.
 */
final class ProjectViews
{
    public const MAX_SAVED = 12;

    /** @var array<string, array{label: string, groups: list<string>}> */
    public const PRESETS = [
        'search' => ['label' => 'Search', 'groups' => ['search']],
        'development' => ['label' => 'Development and Issues', 'groups' => ['development']],
        'revenue' => ['label' => 'Revenue', 'groups' => ['revenue']],
    ];

    /**
     * Known group keys from a query value, in project order, without duplicates.
     *
     * @param  list<mixed>|string|null  $raw
     * @return list<string>
     */
    public static function parse(array|string|null $raw): array
    {
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = preg_split('/\s*,\s*/', trim((string) $raw)) ?: [];
        }
        $found = [];
        foreach ($parts as $part) {
            if (is_string($part) && array_key_exists($part, ProjectLayout::GROUPS)) {
                $found[$part] = true;
            }
        }

        return array_values(array_filter(array_keys(ProjectLayout::GROUPS), fn (string $key): bool => isset($found[$key])));
    }

    /**
     * @param  list<mixed>|string|null  $groups
     * @return array{kind: 'overview'|'metrics'|'preset'|'custom', preset: ?string, groups: list<string>}
     */
    public static function resolve(string $view, array|string|null $groups): array
    {
        if ($view === 'overview') {
            return ['kind' => 'overview', 'preset' => null, 'groups' => []];
        }
        if (isset(self::PRESETS[$view])) {
            return ['kind' => 'preset', 'preset' => $view, 'groups' => self::PRESETS[$view]['groups']];
        }
        $custom = self::parse($groups);
        if ($custom !== []) {
            return ['kind' => 'custom', 'preset' => null, 'groups' => $custom];
        }

        return ['kind' => 'metrics', 'preset' => null, 'groups' => []];
    }

    /**
     * Query parameters that keep the current view when the period or archive changes.
     *
     * @param  array{kind: string, preset: ?string, groups: list<string>}  $resolved
     * @return array<string, string>
     */
    public static function linkQuery(array $resolved): array
    {
        return match ($resolved['kind']) {
            'overview' => ['view' => 'overview'],
            'preset' => ['view' => (string) $resolved['preset']],
            'custom' => ['groups' => implode(',', $resolved['groups'])],
            default => [],
        };
    }
}
