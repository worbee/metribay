<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->string('locale', 10)->nullable();
        });
        Schema::table('annotations', function (Blueprint $t): void {
            $t->dropUnique('annotation_external');
        });
        Schema::rename('annotations', 'project_events');
        Schema::table('project_events', function (Blueprint $t): void {
            $t->unique(['workspace_id', 'source_id', 'origin', 'external_id'], 'project_event_external');
            $t->string('color', 20)->nullable();
            $t->string('recurrence', 10)->nullable();
            $t->date('recurrence_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_events', function (Blueprint $t): void {
            $t->dropUnique('project_event_external');
            $t->dropColumn(['color', 'recurrence', 'recurrence_until']);
        });
        Schema::rename('project_events', 'annotations');
        Schema::table('annotations', function (Blueprint $t): void {
            $t->unique(['workspace_id', 'source_id', 'origin', 'external_id'], 'annotation_external');
        });
        Schema::table('users', function (Blueprint $t): void {
            $t->dropColumn('locale');
        });
    }
};
