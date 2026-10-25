<?php

namespace Metribay\Core\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Connectors\ConnectorRegistry;
use Metribay\Core\Connectors\SourceConfiguration;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Jobs\CollectSource;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectGroup;
use Metribay\Core\Models\Source;
use Metribay\Core\Services\ConfigurationTransfer;
use Metribay\Core\Services\ProjectConfiguration;
use Metribay\Core\Services\ProviderCatalog;
use Metribay\Core\Services\WorkspaceContext;

class SettingsController
{
    private function authorize(Request $request, string $ability = 'edit'): void
    {
        abort_unless(app(WorkspaceAccess::class)->allows($request->user(), app(WorkspaceContext::class)->current(), $ability), 403);
    }

    private function version(Request $request, Project|Source|Connection $model): void
    {
        $request->validate(['version' => ['required', 'integer', 'min:1']]);
        abort_if((int) $request->input('version') !== (int) $model->version, 409, 'This item changed. Reload before saving.');
    }

    public function index(Request $request): View
    {
        return view('metribay::settings', ['projects' => Project::query()->with('sources')->orderBy('name')->get(), 'groups' => ProjectGroup::query()->orderBy('name')->get(), 'connections' => Connection::query()->orderBy('name')->get(), 'sources' => Source::query()->with(['project', 'connection'])->orderBy('project_id')->get(), 'providers' => ProviderCatalog::all(), 'tab' => $request->query('tab', 'projects')]);
    }

    public function projectStore(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $values = $this->projectValues($request);
        $slug = substr(Str::slug($values['name']) ?: 'project', 0, 85);
        $candidate = $slug;
        $i = 2;
        while (Project::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$i++;
        }
        Project::create($values + ['slug' => $candidate]);

        return back()->with('status', 'Project added. Connect a source to start collecting metrics.');
    }

    public function projectUpdate(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $values = $this->projectValues($request);
        DB::transaction(function () use ($request, $values): void {
            $project = Project::query()->lockForUpdate()->whereKey($request->route('project'))->firstOrFail();
            $this->version($request, $project);
            $project->fill($values + ['archived' => $request->has('archived') ? $request->boolean('archived') : $project->archived, 'version' => $project->version + 1])->save();
        });

        return back()->with('status', 'Project updated.');
    }

    /** @return array<string, mixed> */
    private function projectValues(Request $request): array
    {
        $values = $request->validate(['name' => ['required', 'string', 'max:120'], 'domain' => ['nullable', 'string', 'max:253'], 'group_id' => ['nullable', 'integer', Rule::exists('project_groups', 'id')->where('workspace_id', app(WorkspaceContext::class)->id())]]);
        $values['domain'] = ProjectConfiguration::domain($values['domain'] ?? null);

        return $values;
    }

    public function groupStore(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $values = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('project_groups')->where('workspace_id', app(WorkspaceContext::class)->id())]]);
        ProjectGroup::create($values);

        return back()->with('status', 'Group added.');
    }

    public function connectionStore(Request $request): RedirectResponse
    {
        $this->authorize($request, 'manage_connections');
        $values = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('connections')->where('workspace_id', app(WorkspaceContext::class)->id())], 'kind' => ['required', Rule::in(array_keys(ProviderCatalog::all()))], 'values' => ['required', 'array']]);
        $credentials = ProviderCatalog::credentials($values['kind'], $values['values']);
        $connection = new Connection(['name' => $values['name'], 'kind' => $values['kind'], 'workspace_id' => app(WorkspaceContext::class)->id()]);
        $connection->storeSecrets($credentials);
        $connection->save();

        return back()->with('status', 'Connection saved. Credentials are encrypted and are never returned to the browser.');
    }

    public function connectionUpdate(Request $request): RedirectResponse
    {
        $this->authorize($request, 'manage_connections');
        $values = $request->validate(['name' => ['required', 'string', 'max:120'], 'values' => ['nullable', 'array']]);
        DB::transaction(function () use ($request, $values): void {
            $connection = Connection::query()->lockForUpdate()->whereKey($request->route('connection'))->firstOrFail();
            $this->version($request, $connection);
            Validator::make($values, ['name' => ['required', 'string', 'max:120', Rule::unique('connections')->where('workspace_id', app(WorkspaceContext::class)->id())->ignore($connection->id)]])->validate();
            $credentials = array_filter($values['values'] ?? [], fn ($value) => $value !== null && $value !== '');
            if ($credentials) {
                $connection->storeSecrets(ProviderCatalog::credentials($connection->kind, $credentials));
            }$connection->fill(['name' => $values['name'], 'version' => $connection->version + 1])->save();
        });

        return back()->with('status', 'Connection updated.');
    }

    public function sourceStore(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $values = $request->validate(['project_id' => ['required', 'integer'], 'kind' => ['required', Rule::in(array_keys(ProviderCatalog::all()))], 'label' => ['required', 'string', 'max:120'], 'connection_id' => ['nullable', 'integer'], 'reporting_timezone' => ['required', 'timezone'], 'config' => ['required', 'array']]);
        Project::query()->findOrFail($values['project_id']);
        $config = ProviderCatalog::source($values['kind'], $values['config'], $values['reporting_timezone']);
        if ($values['kind'] !== 'wporg') {
            $connection = Connection::query()->find($values['connection_id'] ?? null);
            if (! $connection || $connection->kind !== $values['kind']) {
                throw ValidationException::withMessages(['connection_id' => 'Choose a connection for this provider and workspace.']);
            }
        }
        $identity = $config;
        unset($identity['days'],$identity['limit']);
        ksort($identity);
        $hash = hash('sha256', json_encode([$values['kind'], $identity, $values['reporting_timezone']], JSON_THROW_ON_ERROR));
        if (Source::query()->where('project_id', $values['project_id'])->where('identity_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['config' => 'This resource is already connected to this project.']);
        }
        Source::create(['project_id' => $values['project_id'], 'kind' => $values['kind'], 'label' => $values['label'], 'connection_id' => $values['kind'] === 'wporg' ? null : $values['connection_id'], 'reporting_timezone' => $values['reporting_timezone'], 'config' => $config, 'identity_hash' => $hash]);

        return back()->with('status', 'Source added. Run collection or let the scheduler collect it.');
    }

    public function sourceUpdate(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $values = $request->validate(['label' => ['required', 'string', 'max:120'], 'days' => ['nullable', 'integer', 'min:1', 'max:366'], 'enabled' => ['nullable', 'boolean'], 'connection_id' => ['nullable', 'integer']]);
        DB::transaction(function () use ($request, $values): void {
            $source = Source::query()->lockForUpdate()->whereKey($request->route('source'))->firstOrFail();
            $this->version($request, $source);
            $connectionId = $source->connection_id;
            if ($request->has('connection_id') && $source->kind !== 'wporg') {
                $connection = Connection::query()->whereKey($values['connection_id'] ?? null)->where('kind', $source->kind)->first();
                if (! $connection) {
                    throw ValidationException::withMessages(['connection_id' => 'Choose a connection for this provider and workspace.']);
                }
                $connectionId = $connection->id;
            }
            if ($source->kind !== 'wporg' && ($request->has('enabled') ? $request->boolean('enabled') : $source->enabled) && $connectionId === null) {
                throw ValidationException::withMessages(['connection_id' => 'Connect credentials before enabling collection.']);
            }
            $config = $source->config;
            if (isset($values['days']) && $source->kind !== 'uptimerobot') {
                $config[$source->kind === 'wporg' ? 'limit' : 'days'] = (int) $values['days'];
            }$source->fill(['connection_id' => $connectionId, 'label' => $values['label'], 'enabled' => $request->has('enabled') ? $request->boolean('enabled') : $source->enabled, 'config' => $config, 'version' => $source->version + 1])->save();
        });

        return back()->with('status', 'Source updated. Existing history is retained.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $source = Source::query()->whereKey($request->route('source'))->firstOrFail();
        abort_unless($source->enabled && ! $source->project->archived, 422, 'Enable the source and project before collecting.');
        CollectSource::dispatch(app(WorkspaceContext::class)->id(), $source->id, true);

        return back()->with('status', 'Collection queued. Source health updates when the worker finishes.');
    }

    public function check(Request $request, ConnectorRegistry $connectors): RedirectResponse
    {
        $this->authorize($request, 'manage_connections');
        $source = Source::query()->whereKey($request->route('source'))->firstOrFail();
        try {
            $connectors->for($source->kind)->collect(new SourceConfiguration($source->kind, array_merge($source->config, ['days' => 1, 'limit' => 1, 'period_days' => []]), $source->reporting_timezone), $source->connection?->reveal() ?? [], CarbonImmutable::now('UTC'));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['connection_id' => 'The provider could not confirm access. Check the resource, credentials, and provider permissions.']);
        }

        return back()->with('status', 'Provider access confirmed. This check did not save metrics.');
    }

    public function export(Request $request, ConfigurationTransfer $transfer): JsonResponse
    {
        $this->authorize($request);

        return response()->json($transfer->export())->header('Content-Disposition', 'attachment; filename="metribay-config.json"');
    }

    public function import(Request $request, ConfigurationTransfer $transfer): RedirectResponse
    {
        $this->authorize($request);
        $request->validate(['file' => ['required', 'file', 'max:1024']]);
        try {
            $document = json_decode($request->file('file')->getContent(), true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'Choose a valid Metribay configuration JSON file.']);
        }
        $transfer->import($document);

        return back()->with('status', 'Configuration imported. Sources without an existing matching connection are paused.');
    }
}
