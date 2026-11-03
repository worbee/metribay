<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Top search queries per data source, replaced as one snapshot on every collection.
        Schema::create('search_queries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('source_id');
            $t->unsignedSmallInteger('days');
            $t->boolean('previous')->default(false);
            $t->date('start_date');
            $t->date('end_date');
            $t->text('query');
            $t->double('clicks');
            $t->double('impressions');
            $t->double('position')->nullable();
            $t->boolean('is_partial')->default(false);
            $t->timestamp('collected_at');
            $t->index(['workspace_id', 'source_id', 'days']);
            $t->foreign(['workspace_id', 'source_id'])->references(['workspace_id', 'id'])->on('sources')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
    }
};
