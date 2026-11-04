<?php

namespace Metribay\Core\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Metribay\Core\Connectors\ConnectorRegistry;
use Metribay\Core\Connectors\DeploymentAccess;
use Metribay\Core\Connectors\DiscoveryException;
use Metribay\Core\Connectors\ProviderRequestLimit;
use Metribay\Core\Connectors\ResourceDiscovery;
use Metribay\Core\Connectors\SafeHttpClient;
use Metribay\Core\Contracts\ConnectionCredentials;
use Metribay\Core\Contracts\GoogleAuthorization;
use Metribay\Core\Contracts\PageSpeedCredentials;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Jobs\CollectSource;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\ProjectResources;
use Metribay\Core\Services\ProviderCatalog;
use Metribay\Core\Services\WorkspaceContext;

class SourceSetupController
{
    /** Session root for unfinished setup state. Setup is never resumed after the user leaves it. */
    public const SESSION_KEY = 'source_setup';

    private function authorize(Request $request, string $ability = 'edit'): void
    {
        abort_unless(app(WorkspaceAccess::class)->allows($request->user(), app(WorkspaceContext::class)->current(), $ability), 403);
    }

    private function key(Request $request): string
    {
        return self::SESSION_KEY.'.'.$request->user()->getAuthIdentifier().'.'.app(WorkspaceContext::class)->id();
    }

    public function google(Request $request): RedirectResponse
    {
        $this->authorize($request, 'manage_connections');
        $draft = $request->session()->get($this->key($request), []);
        abort_unless(in_array($draft['kind'] ?? '', ['ga4', 'gsc'], true), 409);
        $client = app(GoogleAuthorization::class)->client();
        $state = Str::random(64);
        $request->session()->put('metribay_google', ['state' => $state, 'user' => $request->user()->getAuthIdentifier(), 'workspace' => app(WorkspaceContext::class)->id(), 'kind' => $draft['kind'], 'client_id' => $client['client_id'], 'expires' => time() + 600]);
        $scope = $draft['kind'] === 'ga4' ? 'https://www.googleapis.com/auth/analytics.readonly' : 'https://www.googleapis.com/auth/webmasters.readonly';

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id' => $client['client_id'], 'redirect_uri' => $client['redirect_uri'], 'response_type' => 'code', 'scope' => $scope, 'access_type' => 'offline', 'prompt' => 'consent', 'state' => $state]));
    }

    public function googleReturn(Request $request, SafeHttpClient $http): RedirectResponse
    {
        $state = $request->session()->get('metribay_google', []);
        $workspace = Workspace::query()->findOrFail($state['workspace'] ?? null);

        return app(WorkspaceContext::class)->run($workspace, fn () => $this->googleCallback($request, $http));
    }

    public function googleCallback(Request $request, SafeHttpClient $http): RedirectResponse
    {
        $this->authorize($request, 'manage_connections');
        $state = $request->session()->pull('metribay_google', []);
        abort_unless(($state['expires'] ?? 0) >= time() && ($state['workspace'] ?? null) === app(WorkspaceContext::class)->id() && ($state['user'] ?? null) === $request->user()->getAuthIdentifier() && is_string($request->input('state')) && hash_equals($state['state'] ?? '', $request->input('state')), 403);
        $target = route('metribay.sources.create', app(WorkspaceContext::class)->current()->slug);
        if (! $request->filled('code')) {
            return redirect($target)->withErrors(['account' => __('Google access was not granted. Try again or use another account.')]);
        }
        $authorization = app(GoogleAuthorization::class);
        $client = $authorization->client();
        abort_unless(($state['client_id'] ?? null) === $client['client_id'], 409, __('Google app configuration changed. Start again.'));
        try {
            $response = $http->request('POST', 'https://oauth2.googleapis.com/token', ['form_params' => ['client_id' => $client['client_id'], 'client_secret' => $client['client_secret'], 'code' => $request->input('code'), 'redirect_uri' => $client['redirect_uri'], 'grant_type' => 'authorization_code']]);
            $credentials = $authorization->credentials((string) $response->json('refresh_token'));
            if (! $response->successful()) {
                throw new \RuntimeException;
            }
            $connection = new Connection(['workspace_id' => app(WorkspaceContext::class)->id(), 'kind' => $state['kind'], 'name' => 'Google · '.now()->format('d M H:i:s')]);
            $connection->storeSecrets($credentials);
            $connection->save();
            $previous = $request->session()->get($this->key($request), []);
            $draft = array_intersect_key($previous, ['project_id' => true]) + ['kind' => $state['kind'], 'connection_id' => $connection->id, 'step' => 'resources', 'expires' => time() + 3600];
            $request->session()->put($this->key($request), $draft);
        } catch (ProviderRequestLimit $exception) {
            return redirect($target)->withErrors(['account' => $exception->getMessage()]);
        } catch (\Throwable) {
            return redirect($target)->withErrors(['account' => __('Google could not connect. Try again and allow read access.')]);
        }

        return redirect($target);
    }

    public function show(Request $request): View|RedirectResponse
    {
        $this->authorize($request);
        $draft = $request->session()->get($this->key($request), []);
        if ($request->filled('project')) {
            // Every "Add data source" click starts a fresh setup for that project.
            $project = Project::query()->where('archived', false)->findOrFail($request->integer('project'));
            $draft = ['project_id' => $project->id, 'expires' => time() + 3600];
        } elseif (($draft['expires'] ?? 0) < time() || ! isset($draft['project_id'])) {
            $request->session()->forget($this->key($request));

            return redirect()->route('metribay.dashboard', app(WorkspaceContext::class)->current()->slug)->with('status', __('Data source setup ended. Open a project and choose Add data source to start again.'));
        }
        $project = Project::query()->where('archived', false)->find($draft['project_id']);
        if (! $project) {
            $request->session()->forget($this->key($request));

            return redirect()->route('metribay.dashboard', app(WorkspaceContext::class)->current()->slug)->with('status', __('That project is no longer available.'));
        }
        $request->session()->put($this->key($request), $draft);
        $added = isset($draft['kind']) ? ProjectResources::added($project->id, $draft['kind']) : [];
        foreach ($draft['resources'] ?? [] as $index => $resource) {
            $draft['resources'][$index]['added'] = in_array(ProjectResources::key($draft['kind'], (string) $resource['id']), $added, true);
        }

        return view('metribay::source-setup', ['providerCounts' => $project->sources()->get(['kind'])->countBy('kind'), 'draft' => $draft, 'providers' => ProviderCatalog::all(), 'project' => $project, 'googleAuthorization' => app(GoogleAuthorization::class), 'connections' => Connection::query()->where('kind', $draft['kind'] ?? '')->orderBy('name')->get()]);
    }

    public function update(Request $request, ResourceDiscovery $discovery): RedirectResponse
    {
        $this->authorize($request);
        $key = $this->key($request);
        $draft = $request->session()->get($key, []);
        if (($draft['expires'] ?? 0) < time()) {
            $draft = [];
        }
        $action = $request->string('action')->toString();
        if ($action === 'restart') {
            $draft = array_intersect_key($draft, ['project_id' => true]);
        } elseif ($action === 'provider') {
            $values = $request->validate(['kind' => ['required', Rule::in(array_keys(ProviderCatalog::all()))]]);
            $draft = array_intersect_key($draft, ['project_id' => true]) + ['kind' => $values['kind'], 'step' => ! ProviderCatalog::requiresConnection($values['kind']) ? 'resources' : 'account'];
        } else {
            abort_unless(isset($draft['kind'], $draft['project_id']), 409, __('Setup expired. Open the project and choose Add data source again.'));
            $kind = $draft['kind'];
            if ($action === 'account') {
                unset($draft['resource'], $draft['resources'], $draft['listed'], $draft['discovery_error']);
                if ($request->filled('connection_id')) {
                    $connection = Connection::query()->where('kind', $kind)->findOrFail($request->integer('connection_id'));
                } else {
                    $this->authorize($request, 'manage_connections');
                    $credentials = ProviderCatalog::credentials($kind, $request->input('values', []));
                    $connection = new Connection(['workspace_id' => app(WorkspaceContext::class)->id(), 'kind' => $kind, 'name' => ProviderCatalog::all()[$kind]['label'].' · '.now()->format('d M H:i:s')]);
                    $connection->storeSecrets($credentials);
                    $connection->save();
                }
                $draft['connection_id'] = $connection->id;
                $draft['step'] = 'resources';
                try {
                    if (! app(WorkspaceAccess::class)->allows($request->user(), app(WorkspaceContext::class)->current(), 'manage_connections')) {
                        throw new \RuntimeException;
                    }
                    $draft['resources'] = $kind === 'pagespeed' ? [] : $discovery->discover($kind, app(ConnectionCredentials::class)->resolve($connection));
                    $draft['listed'] = true;
                    unset($draft['discovery_error']);
                } catch (DiscoveryException $exception) {
                    $draft['discovery_error'] = $exception->getMessage();
                } catch (ProviderRequestLimit $exception) {
                    $draft['discovery_error'] = $exception->getMessage();
                } catch (\Throwable) {
                    $draft['discovery_error'] = __('Use manual entry below, or ask an owner to list resources and check account access.');
                }
            } elseif ($action === 'discover') {
                if ($kind !== 'wporg') {
                    $this->authorize($request, 'manage_connections');
                }
                $request->validate(['search' => ['nullable', 'string', 'max:120']]);
                try {
                    $credentials = $kind === 'wporg' ? [] : app(ConnectionCredentials::class)->resolve(Connection::query()->where('kind', $kind)->findOrFail($draft['connection_id'] ?? null));
                    $draft['search'] = (string) $request->input('search', '');
                    $draft['resources'] = $discovery->discover($kind, $credentials, $draft['search']);
                    $draft['listed'] = true;
                    unset($draft['discovery_error']);
                } catch (DiscoveryException $exception) {
                    throw ValidationException::withMessages(['resource' => $exception->getMessage()]);
                } catch (ProviderRequestLimit $exception) {
                    throw ValidationException::withMessages(['resource' => $exception->getMessage()]);
                } catch (\Throwable) {
                    throw ValidationException::withMessages(['resource' => __('Unable to list resources. Check account access, retry, or use manual entry below.')]);
                }
            } elseif ($action === 'resource') {
                $request->validate(['resource' => ['required', 'integer', 'min:0']]);
                abort_if($kind === 'pagespeed', 422);
                $draft['resource'] = $draft['resources'][$request->integer('resource')] ?? null;
                abort_unless($draft['resource'], 422);
                $this->rejectAddedResource($draft);
                if ($kind === 'ga4') {
                    $this->authorize($request, 'manage_connections');
                    $connection = Connection::query()->where('kind', $kind)->findOrFail($draft['connection_id'] ?? null);
                    try {
                        $draft['resource']['timezone'] = $discovery->googleTimezone(app(ConnectionCredentials::class)->resolve($connection), $draft['resource']['id']);
                    } catch (ProviderRequestLimit $exception) {
                        throw ValidationException::withMessages(['resource' => $exception->getMessage()]);
                    } catch (\Throwable) {
                        throw ValidationException::withMessages(['resource' => __('Unable to read this property’s reporting timezone. Retry or use manual entry.')]);
                    }
                }
                $draft = $this->deploymentEnvironments($request, $draft);
                $draft['step'] = 'confirm';
            } elseif ($action === 'page_speed') {
                abort_unless($kind === 'pagespeed', 422);
                if (! ProviderCatalog::requiresConnection($kind)) {
                    if (! app(PageSpeedCredentials::class)->available()) {
                        throw ValidationException::withMessages(['resource' => __('Page speed is temporarily unavailable. Please try again later.')]);
                    }
                } else {
                    Connection::query()->where('kind', $kind)->findOrFail($draft['connection_id'] ?? null);
                }
                $values = $request->validate(['url' => ['required', 'string', 'max:500'], 'strategy' => ['required', 'in:mobile,desktop']]);
                $config = ProviderCatalog::source($kind, $values, 'UTC');
                unset($draft['discovery_error']);
                $draft['resource'] = ['id' => $config['url'], 'name' => $config['url'], 'domain' => '', 'timezone' => 'UTC', 'strategy' => $config['strategy']];
                $draft['step'] = 'confirm';
            } elseif ($action === 'manual') {
                abort_if($kind === 'pagespeed', 422);
                $values = $request->validate(['resource_id' => ['required', 'string', 'max:500'], 'timezone' => ['required', 'timezone']]);
                $draft['resource'] = ['id' => $values['resource_id'], 'name' => $values['resource_id'], 'domain' => '', 'timezone' => ProviderCatalog::usesSiteTimezone($kind) ? $values['timezone'] : ProviderCatalog::all()[$kind]['default_timezone']];
                $this->rejectAddedResource($draft);
                $draft = $this->deploymentEnvironments($request, $draft);
                $draft['step'] = 'confirm';
            } elseif ($action === 'back') {
                $draft['step'] = $request->input('step') === 'account' && ProviderCatalog::requiresConnection($kind) ? 'account' : 'resources';
            } elseif ($action === 'finish') {
                abort_unless(isset($draft['resource']), 409);
                $values = $request->validate(['label' => ['nullable', 'string', 'max:120'], 'days' => ['required', 'integer', 'min:1', 'max:366'], 'environments' => ['nullable', 'string', 'max:3000']]);
                $field = ProjectResources::field($kind);
                $environments = ProviderCatalog::hasDeploymentEnvironments($kind) ? ProviderCatalog::environments((string) ($values['environments'] ?? '')) : [];
                $config = ProviderCatalog::source($kind, [$field => $draft['resource']['id']] + ($kind === 'pagespeed' ? ['strategy' => $draft['resource']['strategy']] : []) + (in_array($kind, ['uptimerobot', 'pagespeed'], true) ? [] : [($kind === 'wporg' ? 'limit' : 'days') => $values['days']]) + ($environments !== [] ? ['environments' => $environments] : []), $draft['resource']['timezone']);
                if (! ProviderCatalog::requiresConnection($kind)) {
                    if ($kind === 'pagespeed' && ! app(PageSpeedCredentials::class)->available()) {
                        throw ValidationException::withMessages(['resource' => __('Page speed is temporarily unavailable. Please try again later.')]);
                    }
                    unset($draft['connection_id']);
                }
                if (ProviderCatalog::requiresConnection($kind)) {
                    Connection::query()->where('kind', $kind)->findOrFail($draft['connection_id'] ?? null);
                }
                $source = DB::transaction(function () use ($values, $draft, $kind, $config) {
                    $project = Project::query()->where('archived', false)->lockForUpdate()->findOrFail($draft['project_id']);
                    $hash = ProviderCatalog::identityHash($kind, $config, $draft['resource']['timezone']);
                    if (($kind !== 'pagespeed' && ProjectResources::contains($project->id, $kind, (string) $config[ProjectResources::field($kind)]))
                        || Source::query()->where('project_id', $project->id)->where('identity_hash', $hash)->exists()) {
                        throw ValidationException::withMessages(['resource' => __('This data source is already in this project.')]);
                    }

                    return Source::create(['project_id' => $project->id, 'kind' => $kind, 'label' => ($values['label'] ?? '') ?: mb_substr(ProviderCatalog::all()[$kind]['label'].' · '.$draft['resource']['name'], 0, 120), 'connection_id' => $draft['connection_id'] ?? null, 'reporting_timezone' => $draft['resource']['timezone'], 'config' => $config, 'identity_hash' => $hash]);
                });
                CollectSource::dispatch(app(WorkspaceContext::class)->id(), $source->id, true)->afterCommit();
                $request->session()->forget($key);

                return redirect()->route('metribay.projects.show', ['workspace' => app(WorkspaceContext::class)->current()->slug, 'project' => $source->project_id])->with('status', __('Data source added. Your first import is queued; this page updates automatically.'));
            } else {
                abort(422);
            }
        }
        $draft['expires'] = time() + 3600;
        $request->session()->put($key, $draft);

        return redirect()->route('metribay.sources.create', app(WorkspaceContext::class)->current()->slug);
    }

    /** @param array<string, mixed> $draft */
    private function rejectAddedResource(array $draft): void
    {
        if (ProjectResources::contains((int) $draft['project_id'], $draft['kind'], (string) $draft['resource']['id'])) {
            throw ValidationException::withMessages(['resource' => __('This data source is already in this project.')]);
        }
    }

    /**
     * Lists the repository's deployment environments to suggest which deployments become events.
     * Optional: failures only explain why no suggestions are shown.
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function deploymentEnvironments(Request $request, array $draft): array
    {
        unset($draft['environments'], $draft['environments_error']);
        $connection = Connection::query()->where('kind', $draft['kind'])->find($draft['connection_id'] ?? null);
        if (! ProviderCatalog::hasDeploymentEnvironments($draft['kind']) || ! $connection
            || ! app(WorkspaceAccess::class)->allows($request->user(), app(WorkspaceContext::class)->current(), 'manage_connections')) {
            return $draft;
        }
        try {
            $draft['environments'] = app(ConnectorRegistry::class)->environments($draft['kind'])->environments((string) $draft['resource']['id'], app(ConnectionCredentials::class)->resolve($connection), CarbonImmutable::now('UTC'));
        } catch (DeploymentAccess|ProviderRequestLimit $exception) {
            $draft['environments_error'] = __($exception->getMessage());
        } catch (\Throwable) {
            $draft['environments_error'] = self::environmentsUnavailable($draft['kind']);
        }

        return $draft;
    }

    public static function environmentsUnavailable(string $kind): string
    {
        return $kind === 'azure_devops' ? __('Azure DevOps could not list environments. Check the connected account, then try again.')
            : __('GitHub could not list deployments. Check the connected account, then try again.');
    }
}
