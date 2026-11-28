<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Calendar-month archive of audience breakdowns. A month is written once, after its dates are final,
        // and kept. Rolling 7/30/90-day lists stay in breakdown_rows and are never treated as a calendar month.
        Schema::create('breakdown_months', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('source_id');
            $t->string('dimension', 32);
            $t->date('month');
            $t->date('start_date');
            $t->date('end_date');
            $t->unsignedInteger('item_count');
            $t->boolean('is_partial')->default(false);
            $t->timestamp('collected_at');
            $t->unique(['workspace_id', 'source_id', 'dimension', 'month']);
            $t->foreign(['workspace_id', 'source_id'])->references(['workspace_id', 'id'])->on('sources')->cascadeOnDelete();
        });
        Schema::create('breakdown_month_rows', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('breakdown_month_id')->constrained()->cascadeOnDelete();
            $t->text('item');
            $t->double('visits')->nullable();
            $t->double('visitors')->nullable();
            $t->double('conversions')->nullable();
        });
        // One frozen report per project and calendar month. payload is replaced only until sealed_at is set.
        Schema::create('monthly_reports', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('project_id');
            $t->date('month');
            $t->json('payload');
            $t->boolean('has_data')->default(false);
            $t->timestamp('sealed_at')->nullable();
            $t->timestamps();
            $t->unique(['workspace_id', 'project_id', 'month']);
            $t->foreign(['workspace_id', 'project_id'])->references(['workspace_id', 'id'])->on('projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_reports');
        Schema::dropIfExists('breakdown_month_rows');
        Schema::dropIfExists('breakdown_months');
    }
};
