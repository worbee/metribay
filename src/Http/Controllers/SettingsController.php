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
use Metribay\Core\Connectors\DeploymentAccess;
use Metribay\Core\Connectors\ProviderRequestLimit;
use Metribay\Core\Connectors\SourceConfiguration;
use Metribay\Core\Contracts\ConnectionCredentials;
use Metribay\Core\Contracts\PageSpeedCredentials;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Jobs\CollectSource;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\ProjectGroup;
use Metribay\Core\Models\Source;
use Metribay\Core\Services\ConfigurationTransfer;
use Metribay\Core\Services\HistoryExport;
use Metribay\Core\Services\ProviderCatalog;
use Metribay\Core\Services\WorkspaceContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        if ($values['kind'] === 'paddle') {
            throw ValidationException::withMessages(['kind' => __('Add Paddle from the project’s data source setup.')]);
        }
        Project::query()->findOrFail($values['project_id']);
        $config = ProviderCatalog::source($values['kind'], $values['config'], $values['reporting_timezone']);
        $connectionId = null;
        if (ProviderCatalog::requiresConnection($values['kind']) || (ProviderCatalog::connectionOptional($values['kind']) && ($values['connection_id'] ?? null))) {
            $connection = Connection::query()->find($values['connection_id'] ?? null);
            if (! $connection || $connection->kind !== $values['kind']) {
                throw ValidationException::withMessages(['connection_id' => __('Choose a connected account for this provider.')]);
            }
            $connectionId = $connection->id;
        }
        $hash = ProviderCatalog::identityHash($values['kind'], $config, $values['reporting_timezone']);
        if (Source::query()->where('project_id', $values['project_id'])->where('identity_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['config' => __('This data source is already in this project.')]);
        }
        Source::create(['project_id' => $values['project_id'], 'kind' => $values['kind'], 'label' => $values['label'], 'connection_id' => $connectionId, 'reporting_timezone' => $values['reporting_timezone'], 'config' => $config, 'identity_hash' => $hash]);

        return back()->with('status', __('Data source added. Run collection or let the scheduler collect it.'));
    }

    public function sourceUpdate(Request $request): RedirectResponse
    {
        $this->authorize($request);
        $values = $request->validate(['label' => ['required', 'string', 'max:120'], 'days' => ['nullable', 'integer', 'min:1', 'max:366'], 'enabled' => ['nullable', 'boolean'], 'connection_id' => ['nullable', 'integer'], 'environments' => ['nullable', 'string', 'max:3000']]);
        $changedEnvironments = DB::transaction(function () use ($request, $values): ?Source {
            $source = Source::query()->lockForUpdate()->whereKey($request->route('source'))->firstOrFail();
            $this->version($request, $source);
            if ($source->kind === 'paddle') {
                $this->authorize($request, 'manage_access');
            }
            $connectionId = $source->connection_id;
            if ($request->has('connection_id') && (ProviderCatalog::requiresConnection($source->kind) || ProviderCatalog::connectionOptional($source->kind))) {
                if (ProviderCatalog::connectionOptional($source->kind) && ($values['connection_id'] ?? null) === null) {
                    $connectionId = null;
                } else {
                    $connection = Connection::query()->whereKey($values['connection_id'] ?? null)->where('kind', $source->kind)->first();
                    if (! $connection) {
                        throw ValidationException::withMessages(['connection_id' => __('Choose a connected account for this provider.')]);
                    }
                    $connectionId = $connection->id;
                }
            }
            if (ProviderCatalog::requiresConnection($source->kind) && ($request->has('enabled') ? $request->boolean('enabled') : $source->enabled) && $connectionId === null) {
                throw ValidationException::withMessages(['connection_id' => __('Choose a connected account before enabling collection.')]);
            }
            $config = $source->config;
            if (isset($values['days']) && ! in_array($source->kind, ['uptimerobot', 'pagespeed', 'github_releases'], true)) {
                $config[$source->kind === 'wporg' ? 'limit' : 'days'] = (int) $values['days'];
            }
            $environmentsChanged = false;
            if (ProviderCatalog::hasDeploymentEnvironments($source->kind) && $request->has('environments')) {
                $environments = ProviderCatalog::environments((string) ($values['environments'] ?? ''));
                $environmentsChanged = $environments !== ($config['environments'] ?? []);
                unset($config['environments']);
                if ($environments !== []) {
                    $config['environments'] = $environments;
                }
                if ($environmentsChanged) {
                    // Deselected environments must not linger beyond the collection window; the next sync reimports the rest.
                    ProjectEvent::query()->where('source_id', $source->id)->where('origin', ProviderCatalog::deploymentOrigin($source->kind))->delete();
                }
            }
            $source->fill(['connection_id' => $connectionId, 'label' => $values['label'], 'enabled' => $request->has('enabled') ? $request->boolean('enabled') : $source->enabled, 'config' => $config, 'version' => $source->version + 1])->save();

            return $environmentsChanged ? $source : null;
        });
        if ($changedEnvironments?->enabled && ! $changedEnvironments->project->archived) {
            CollectSource::dispatch(app(WorkspaceContext::class)->id(), $changedEnvironments->id, true);

            return back()->with('status', __('Data source updated. Deployment events are being imported again.'));
        }

        return back()->with('status', __('Data source updated. Existing history is retained.'));
    }

    public function environments(Request $request, ConnectorRegistry $connectors): RedirectResponse
    {
        $this->authorize($request, 'manage_connections');
        $source = Source::query()->whereKey($request->route('source'))->whereIn('kind', ['github', 'azure_devops'])->firstOrFail();
        $connection = $source->connection;
        abort_if($connection === null, 422, __('Choose a connected account first.'));
        $redirect = redirect()->to(url()->previous().'#source-'.$source->id)->withInput(['source_form' => (string) $source->id]);
        try {
            $names = $connectors->environments($source->kind)->environments((string) $source->config['repository'], app(ConnectionCredentials::class)->resolve($connection), CarbonImmutable::now('UTC'));
        } catch (DeploymentAccess|ProviderRequestLimit $exception) {
            return $redirect->withErrors(['environments' => __($exception->getMessage())]);
        } catch (\Throwable) {
            return $redirect->withErrors(['environments' => SourceSetupController::environmentsUnavailable($source->kind)]);
        }

        return $redirect->with('environments_found', ['source' => $source->id, 'names' => $names]);
    }

    public function check(Request $request, ConnectorRegistry $connectors): RedirectResponse
    {
        $this->authorize($request, 'manage_connections');
        $source = Source::query()->whereKey($request->route('source'))->firstOrFail();
        try {
            $connectors->for($source->kind)->collect(new SourceConfiguration($source->kind, array_merge($source->config, ['days' => 1, 'limit' => 1, 'period_days' => []]), $source->reporting_timezone), $source->connection ? app(ConnectionCredentials::class)->resolve($source->connection) : ($source->kind === 'pagespeed' ? app(PageSpeedCredentials::class)->resolve() : []), CarbonImmutable::now('UTC'));
        } catch (ProviderRequestLimit $exception) {
            throw ValidationException::withMessages(['connection_id' => $exception->getMessage()]);
        } catch (DeploymentAccess $exception) {
            throw ValidationException::withMessages(['connection_id' => __($exception->getMessage())]);
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

    /** Streams the workspace's metric history as CSV, so it can be kept outside Metribay. */
    public function exportHistory(Request $request, HistoryExport $export): StreamedResponse
    {
        $this->authorize($request);
        $workspace = app(WorkspaceContext::class)->current();

        return response()->streamDownload(function () use ($export, $workspace): void {
            $stream = fopen('php://output', 'wb');
            $export->write($workspace, $stream);
            fclose($stream);
        }, 'metribay-history-'.$workspace->slug.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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
        $shareRevenue = $request->boolean('share_revenue') && app(WorkspaceAccess::class)->allows($request->user(), app(WorkspaceContext::class)->current(), 'manage_access');
        $transfer->import($document, $shareRevenue);

        return back()->with('status', __('Configuration imported. Data sources without a matching connected account are paused.'));
    }
}
