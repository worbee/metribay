<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hidden_charts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('project_id');
            $t->unsignedBigInteger('source_id');
            $t->string('metric_key', 64);
            $t->timestamps();
            $t->unique(['project_id', 'source_id', 'metric_key']);
            $t->foreign(['workspace_id', 'project_id'])->references(['workspace_id', 'id'])->on('projects')->cascadeOnDelete();
            $t->foreign(['workspace_id', 'source_id'])->references(['workspace_id', 'id'])->on('sources')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hidden_charts');
    }
};
