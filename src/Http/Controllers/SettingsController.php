<?php

namespace Metribay\Core\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Connectors\ConnectorRegistry;
use Metribay\Core\Connectors\ProviderRequestLimit;
use Metribay\Core\Connectors\SourceConfiguration;
use Metribay\Core\Contracts\ConnectionCredentials;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Jobs\CollectSource;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectGroup;
use Metribay\Core\Models\Source;
use Metribay\Core\Services\ConfigurationTransfer;
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
        abort_if((int) $request->input('version') !== (int) $model->version, 409, __('This item changed. Reload before saving.'));
    }

    public function index(Request $request): View|RedirectResponse
    {
        // Data sources now live under their project; older links to separate tabs land on the new ones.
        $legacy = ['sources' => 'projects', 'connections' => 'accounts'];
        if (array_key_exists((string) $request->query('tab'), $legacy)) {
            return redirect()->route('metribay.settings', array_filter(['workspace' => app(WorkspaceContext::class)->current()->slug, 'tab' => $legacy[$request->query('tab')], 'create' => $request->query('create')]));
        }

        return view('metribay::settings', ['projects' => Project::query()->with('sources')->orderBy('name')->get(), 'groups' => ProjectGroup::query()->orderBy('name')->get(), 'connections' => Connection::query()->orderBy('name')->get(), 'sources' => Source::query()->with(['project', 'connection'])->orderBy('project_id')->get(), 'providers' => ProviderCatalog::all(), 'tab' => $request->query('tab', 'projects')]);
    }

    public function groupStore(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $values = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('project_groups')->where('workspace_id', app(WorkspaceContext::class)->id())]]);
        ProjectGroup::create($values);

        return back()->with('status', __('Group added.'));
    }

    public function connectionStore(Request $request): RedirectResponse
    {
        $this->authorize($request, 'manage_connections');
        $values = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('connections')->where('workspace_id', app(WorkspaceContext::class)->id())], 'kind' => ['required', Rule::in(array_keys(ProviderCatalog::all()))], 'values' => ['required', 'array']]);
        $credentials = ProviderCatalog::credentials($values['kind'], $values['values']);
        $connection = new Connection(['name' => $values['name'], 'kind' => $values['kind'], 'workspace_id' => app(WorkspaceContext::class)->id()]);
        $connection->storeSecrets($credentials);
        $connection->save();

        return back()->with('status', __('Account connected. Credentials are encrypted and are never returned to the browser.'));
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

        return back()->with('status', __('Connected account updated.'));
    }

    public function connectionDestroy(Request $request): RedirectResponse
    {
        $this->authorize($request, 'manage_connections');
        DB::transaction(function () use ($request): void {
            $connection = Connection::query()->lockForUpdate()->whereKey($request->route('connection'))->firstOrFail();
            // Removing an account in use would silently stop its data sources; ask for a switch first.
            if (Source::query()->where('connection_id', $connection->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['connection' => __('This account is used by data sources. Switch them to another account first.')]);
            }
            $connection->delete();
        });

        return back()->with('status', __('Connected account removed. Its saved credentials were deleted.'));
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
                throw ValidationException::withMessages(['connection_id' => __('Choose a connected account for this provider.')]);
            }
        }
        $identity = $config;
        unset($identity['days'],$identity['limit']);
        ksort($identity);
        $hash = hash('sha256', json_encode([$values['kind'], $identity, $values['reporting_timezone']], JSON_THROW_ON_ERROR));
        if (Source::query()->where('project_id', $values['project_id'])->where('identity_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['config' => __('This data source is already in this project.')]);
        }
        Source::create(['project_id' => $values['project_id'], 'kind' => $values['kind'], 'label' => $values['label'], 'connection_id' => $values['kind'] === 'wporg' ? null : $values['connection_id'], 'reporting_timezone' => $values['reporting_timezone'], 'config' => $config, 'identity_hash' => $hash]);

        return back()->with('status', __('Data source added. Run collection or let the scheduler collect it.'));
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
                    throw ValidationException::withMessages(['connection_id' => __('Choose a connected account for this provider.')]);
                }
                $connectionId = $connection->id;
            }
            if ($source->kind !== 'wporg' && ($request->has('enabled') ? $request->boolean('enabled') : $source->enabled) && $connectionId === null) {
                throw ValidationException::withMessages(['connection_id' => __('Choose a connected account before enabling collection.')]);
            }
            $config = $source->config;
            if (isset($values['days']) && $source->kind !== 'uptimerobot') {
                $config[$source->kind === 'wporg' ? 'limit' : 'days'] = (int) $values['days'];
            }$source->fill(['connection_id' => $connectionId, 'label' => $values['label'], 'enabled' => $request->has('enabled') ? $request->boolean('enabled') : $source->enabled, 'config' => $config, 'version' => $source->version + 1])->save();
        });

        return back()->with('status', __('Data source updated. Existing history is retained.'));
    }

    public function sync(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $source = Source::query()->whereKey($request->route('source'))->firstOrFail();
        abort_unless($source->enabled && ! $source->project->archived, 422, __('Enable the data source and project before collecting.'));
        CollectSource::dispatch(app(WorkspaceContext::class)->id(), $source->id, true);

        return back()->with('status', __('Collection queued. The status updates when the import finishes.'));
    }

    public function check(Request $request, ConnectorRegistry $connectors): RedirectResponse
    {
        $this->authorize($request, 'manage_connections');
        $source = Source::query()->whereKey($request->route('source'))->firstOrFail();
        try {
            $connectors->for($source->kind)->collect(new SourceConfiguration($source->kind, array_merge($source->config, ['days' => 1, 'limit' => 1, 'period_days' => []]), $source->reporting_timezone), $source->connection ? app(ConnectionCredentials::class)->resolve($source->connection) : [], CarbonImmutable::now('UTC'));
        } catch (ProviderRequestLimit $exception) {
            throw ValidationException::withMessages(['connection_id' => $exception->getMessage()]);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['connection_id' => __('The provider could not confirm access. Check the resource, the connected account and provider permissions.')]);
        }

        return back()->with('status', __('Provider access confirmed. This check did not save metrics.'));
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
            throw ValidationException::withMessages(['file' => __('Choose a valid Metribay configuration JSON file.')]);
        }
        $transfer->import($document);

        return back()->with('status', __('Configuration imported. Data sources without a matching connected account are paused.'));
    }
}
