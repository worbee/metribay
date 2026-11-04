<?php

namespace Metribay\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectGroup;
use Metribay\Core\Models\Source;

class ConfigurationTransfer
{
    /** @return array<string, mixed> */
    public function export(): array
    {
        return ['format' => 'metribay', 'version' => 1, 'projects' => Project::query()->with(['group', 'sources.connection'])->get()->map(fn ($project) => ['slug' => $project->slug, 'name' => $project->name, 'domain' => $project->domain, 'group' => $project->group?->name, 'archived' => $project->archived, 'sources' => $project->sources->map(fn ($source) => ['kind' => $source->kind, 'label' => $source->label, 'config' => $source->config, 'reporting_timezone' => $source->reporting_timezone, 'enabled' => $source->enabled, 'connection' => $source->connection?->name])->all()])->all()];
    }

    public function import(mixed $document, bool $shareRevenue = false): void
    {
        if (! is_array($document)) {
            throw ValidationException::withMessages(['file' => __('Invalid configuration.')]);
        }
        Validator::make($document, ['format' => ['required', 'in:metribay'], 'version' => ['required', 'in:1'], 'projects' => ['present', 'array', 'max:100'], 'projects.*.slug' => ['required', 'alpha_dash', 'max:100'], 'projects.*.name' => ['required', 'string', 'max:120'], 'projects.*.domain' => ['nullable', 'string', 'max:253'], 'projects.*.group' => ['nullable', 'string', 'max:120'], 'projects.*.archived' => ['required', 'boolean'], 'projects.*.sources' => ['present', 'array', 'max:100'], 'projects.*.sources.*.kind' => ['required', 'string'], 'projects.*.sources.*.label' => ['required', 'string', 'max:120'], 'projects.*.sources.*.config' => ['required', 'array'], 'projects.*.sources.*.reporting_timezone' => ['required', 'timezone'], 'projects.*.sources.*.enabled' => ['required', 'boolean'], 'projects.*.sources.*.connection' => ['nullable', 'string', 'max:120']])->validate();
        DB::transaction(function () use ($document, $shareRevenue): void {
            foreach ($document['projects'] as $item) {
                $group = ! empty($item['group']) ? ProjectGroup::firstOrCreate(['name' => $item['group']]) : null;
                $project = Project::query()->firstOrNew(['slug' => $item['slug']]);
                $project->fill(['name' => $item['name'], 'domain' => ProjectConfiguration::domain($item['domain'] ?? null), 'group_id' => $group?->id, 'archived' => $item['archived'], 'version' => ($project->version ?? 0) + 1])->save();
                foreach ($item['sources'] as $entry) {
                    if ($entry['kind'] === 'paddle' && ! $shareRevenue) {
                        throw ValidationException::withMessages(['file' => __('A workspace owner must confirm sharing before importing Paddle sources.')]);
                    }
                    $config = ProviderCatalog::source($entry['kind'], $entry['config'], $entry['reporting_timezone']);
                    $hash = ProviderCatalog::identityHash($entry['kind'], $config, $entry['reporting_timezone']);
                    $connection = ! empty($entry['connection']) ? Connection::query()->where('name', $entry['connection'])->where('kind', $entry['kind'])->first() : null;
                    $source = Source::query()->firstOrNew(['project_id' => $project->id, 'identity_hash' => $hash]);
                    $source->fill(['kind' => $entry['kind'], 'label' => $entry['label'], 'config' => $config, 'reporting_timezone' => $entry['reporting_timezone'], 'enabled' => $entry['enabled'] && (! ProviderCatalog::requiresConnection($entry['kind']) || $connection !== null), 'connection_id' => $connection?->id, 'version' => ($source->version ?? 0) + 1])->save();
                }
            }
        });
    }
}
