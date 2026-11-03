<?php

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Metribay\Core\Demo\SamplePortfolio;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\SyncRun;
use Metribay\Core\Models\Workspace;

/**
 * Stores the synthetic sample portfolio (docs/DEMO_DATA.md) through the same MetricStore path as real collection.
 * Sample sources have no connected account and are only active when collection is switched off for the installation.
 */
class DemoData
{
    /** Sign-in of the dedicated demo database. It exists only there; production refuses demo data. */
    public const EMAIL = 'demo@metribay.example';

    public const PASSWORD = 'sample-portfolio-only';

    public function seed(Command $command, ?string $email, bool $fresh = false, int $seed = SamplePortfolio::SEED, int $days = SamplePortfolio::DAYS): int
    {
        if ($fresh) {
            $connection = (string) config('database.default');
            $database = (string) config("database.connections.{$connection}.database");
            if (config("database.connections.{$connection}.driver") !== 'sqlite' || ! str_ends_with($database, 'demo.sqlite')) {
                $command->error('--fresh only rebuilds a dedicated SQLite file whose name ends in demo.sqlite.');

                return Command::FAILURE;
            }
            if (config('metribay.collection.enabled', true)) {
                $command->error('Switch collection off for the demo database first: METRIBAY_COLLECTION_ENABLED=false.');

                return Command::FAILURE;
            }
            if (! is_file($database)) {
                touch($database);
            }
            Artisan::call('migrate:fresh', ['--force' => true]);
            $userModel = config('auth.providers.users.model');
            $user = $userModel::query()->forceCreate(['name' => 'Sample owner', 'email' => self::EMAIL, 'password' => self::PASSWORD, 'email_verified_at' => now(), 'locale' => 'en']);
            $workspace = app(WorkspaceProvisioner::class)->create($user, 'Sample portfolio');
        } else {
            $userModel = config('auth.providers.users.model');
            $user = $userModel::query()->when($email, fn ($q) => $q->where('email', $email))->first();
            if (! $user) {
                $command->error('Create an owner first with metribay:install.');

                return Command::FAILURE;
            }
            $workspace = Workspace::query()->where('owner_id', $user->id)->firstOrFail();
        }
        $projects = $this->populate($workspace, $seed, null, $days, ! $fresh);
        $active = ! config('metribay.collection.enabled', true);
        $command->info("{$projects} sample projects added".($active ? '.' : '; their data sources are paused, so nothing is ever collected for them.'));
        if ($fresh) {
            $command->line('Sign in as '.self::EMAIL.' with the password '.self::PASSWORD.'.');
        }

        return Command::SUCCESS;
    }

    /**
     * Adds the sample portfolio to a workspace, replacing samples added before. Also used as a test fixture.
     * $labelled adds "· Sample" to project names and a sample- prefix to slugs, for workspaces that hold real projects.
     */
    public function populate(Workspace $workspace, int $seed = SamplePortfolio::SEED, ?CarbonImmutable $now = null, int $days = SamplePortfolio::DAYS, bool $labelled = true): int
    {
        $now ??= CarbonImmutable::now('UTC');
        $samples = (new SamplePortfolio($seed, $now, $days))->projects();
        // Without a connected account, an active sample source would fail (or, for public providers, request fictional data).
        $active = ! config('metribay.collection.enabled', true);
        $store = app(MetricStore::class);

        return app(WorkspaceContext::class)->run($workspace, function () use ($samples, $labelled, $active, $store): int {
            $slugs = array_map(fn ($sample): string => ($labelled ? 'sample-' : '').$sample->slug, $samples);
            Project::query()->whereIn('slug', $slugs)->where('domain', 'like', '%.example')->get()->each(fn (Project $project) => $project->delete());
            foreach ($samples as $sample) {
                DB::transaction(function () use ($sample, $labelled, $active, $store): void {
                    $project = Project::query()->create(['name' => $sample->name.($labelled ? ' · Sample' : ''), 'slug' => ($labelled ? 'sample-' : '').$sample->slug, 'domain' => $sample->domain]);
                    foreach ($sample->sources as $definition) {
                        $source = Source::query()->create(['project_id' => $project->id, 'kind' => $definition->kind, 'label' => $definition->label, 'config' => $definition->config,
                            'identity_hash' => ProviderCatalog::identityHash($definition->kind, $definition->config, $definition->timezone), 'reporting_timezone' => $definition->timezone, 'enabled' => $active]);
                        $rows = 0;
                        foreach ($definition->collections as $collection) {
                            $rows += $store->persist($source, $collection['result'], $collection['at']);
                        }
                        $started = $definition->lastSuccess->subSeconds(40);
                        $source->forceFill(['last_started_at' => $started, 'last_success_at' => $definition->lastSuccess])->save();
                        SyncRun::query()->create(['source_id' => $source->id, 'started_at' => $started, 'finished_at' => $definition->lastSuccess, 'status' => 'success', 'rows_written' => $rows]);
                    }
                    foreach ($sample->events as $event) {
                        ProjectEvent::query()->create(['project_id' => $project->id, 'origin' => 'manual'] + $event);
                    }
                });
            }

            return count($samples);
        });
    }
}
