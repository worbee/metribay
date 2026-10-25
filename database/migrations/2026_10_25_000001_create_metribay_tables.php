<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->text('two_factor_secret')->nullable();
            $t->text('two_factor_recovery_codes')->nullable();
            $t->timestamp('two_factor_confirmed_at')->nullable();
        });
        Schema::create('workspaces', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $t->string('name', 120);
            $t->string('slug', 80)->unique();
            $t->timestamps();
        });
        Schema::create('project_groups', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->timestamps();
            $t->unique(['workspace_id', 'name']);
            $t->unique(['workspace_id', 'id']);
        });
        Schema::create('projects', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('group_id')->nullable();
            $t->string('name', 120);
            $t->string('slug', 100);
            $t->string('domain', 253)->nullable();
            $t->boolean('archived')->default(false);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['workspace_id', 'slug']);
            $t->unique(['workspace_id', 'id']);
            $t->foreign(['workspace_id', 'group_id'])->references(['workspace_id', 'id'])->on('project_groups');
        });
        Schema::create('connections', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->string('kind', 30);
            $t->text('encrypted_payload');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['workspace_id', 'name']);
            $t->unique(['workspace_id', 'id']);
        });
        Schema::create('sources', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('connection_id')->nullable();
            $t->string('kind', 30);
            $t->string('label', 120);
            $t->json('config');
            $t->string('identity_hash', 64);
            $t->string('reporting_timezone', 80)->default('UTC');
            $t->boolean('enabled')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('last_started_at')->nullable();
            $t->timestamp('last_success_at')->nullable();
            $t->timestamp('last_error_at')->nullable();
            $t->unsignedInteger('consecutive_failures')->default(0);
            $t->string('error_code', 80)->nullable();
            $t->date('latest_data_date')->nullable();
            $t->date('finalized_through')->nullable();
            $t->timestamps();
            $t->unique(['workspace_id', 'id']);
            $t->unique(['workspace_id', 'project_id', 'identity_hash']);
            $t->foreign(['workspace_id', 'project_id'])->references(['workspace_id', 'id'])->on('projects')->cascadeOnDelete();
            $t->foreign(['workspace_id', 'connection_id'])->references(['workspace_id', 'id'])->on('connections');
        });
        Schema::create('daily_metrics', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('source_id');
            $t->string('metric', 40);
            $t->date('date');
            $t->double('value');
            $t->boolean('is_partial')->default(false);
            $t->timestamp('collected_at');
            $t->timestamp('revised_at')->nullable();
            $t->timestamp('observed_at')->nullable();
            $t->timestamp('window_start')->nullable();
            $t->timestamp('window_end')->nullable();
            $t->timestamps();
            $t->unique(['workspace_id', 'source_id', 'metric', 'date']);
            $t->foreign(['workspace_id', 'source_id'])->references(['workspace_id', 'id'])->on('sources')->cascadeOnDelete();
        });
        Schema::create('period_metrics', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('source_id');
            $t->string('metric', 40);
            $t->date('start_date');
            $t->date('end_date');
            $t->double('value');
            $t->boolean('is_partial')->default(false);
            $t->timestamp('collected_at');
            $t->timestamps();
            $t->unique(['workspace_id', 'source_id', 'metric', 'start_date', 'end_date'], 'period_identity');
            $t->foreign(['workspace_id', 'source_id'])->references(['workspace_id', 'id'])->on('sources')->cascadeOnDelete();
        });
        Schema::create('annotations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('source_id')->nullable();
            $t->date('date');
            $t->timestamp('occurred_at')->nullable();
            $t->string('title', 200);
            $t->text('body')->nullable();
            $t->text('url')->nullable();
            $t->string('origin', 30)->default('manual');
            $t->string('external_id', 120)->nullable();
            $t->timestamps();
            $t->unique(['workspace_id', 'source_id', 'origin', 'external_id'], 'annotation_external');
            $t->foreign(['workspace_id', 'project_id'])->references(['workspace_id', 'id'])->on('projects')->cascadeOnDelete();
            $t->foreign(['workspace_id', 'source_id'])->references(['workspace_id', 'id'])->on('sources')->cascadeOnDelete();
        });
        Schema::create('sync_runs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('source_id');
            $t->timestamp('started_at');
            $t->timestamp('finished_at')->nullable();
            $t->string('status', 20);
            $t->unsignedInteger('rows_written')->default(0);
            $t->string('error_code', 80)->nullable();
            $t->timestamps();
            $t->foreign(['workspace_id', 'source_id'])->references(['workspace_id', 'id'])->on('sources')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['sync_runs', 'annotations', 'period_metrics', 'daily_metrics', 'sources', 'connections', 'projects', 'project_groups', 'workspaces'] as $table) {
            Schema::dropIfExists($table);
        } Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']));
    }
};
