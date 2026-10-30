<?php

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\ProjectEvent;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;

class DemoData
{
    public function seed(Command $command, ?string $email): int
    {
        $userModel = config('auth.providers.users.model');
        $user = $userModel::query()->when($email, fn ($q) => $q->where('email', $email))->first();
        if (! $user) {
            $command->error('Create an owner first with metribay:install.');

            return Command::FAILURE;
        }$workspace = Workspace::query()->where('owner_id', $user->id)->firstOrFail();
        app(WorkspaceContext::class)->run($workspace, function (): void {
            foreach (['Fieldnotes', 'Northstar', 'Quiet Studio'] as $index => $name) {
                $project = Project::query()->firstOrCreate(['slug' => str($name)->slug()->toString()], ['name' => $name.' · Demo', 'domain' => 'example.com']);
                $source = Source::query()->firstOrCreate(['project_id' => $project->id, 'identity_hash' => hash('sha256', 'demo-'.$index)], ['kind' => 'wporg', 'label' => 'Plugin downloads · Demo', 'config' => ['slug' => 'synthetic-demo', 'limit' => 180], 'reporting_timezone' => 'UTC', 'enabled' => false]);
                $rows = [];
                $today = CarbonImmutable::now('UTC')->startOfDay();
                for ($i = 179; $i >= 0; $i--) {
                    $rows[] = ['metric' => 'downloads', 'date' => $today->subDays($i)->toDateString(), 'value' => (float) (40 + $index * 25 + ($i * 17 + $index * 11) % 60), 'is_partial' => $i === 0];
                }app(MetricStore::class)->persist($source, new CollectionResult($rows), CarbonImmutable::now('UTC'));
                $source->forceFill(['last_success_at' => now()])->save();
                ProjectEvent::query()->firstOrCreate(['project_id' => $project->id, 'title' => 'Demo release'], ['date' => $today->subDays(5)->toDateString(), 'body' => 'Synthetic example data for exploring Metribay.', 'origin' => 'manual']);
            }
        });
        $command->info('Synthetic demo projects added; collection is disabled for these sources.');

        return Command::SUCCESS;
    }
}
