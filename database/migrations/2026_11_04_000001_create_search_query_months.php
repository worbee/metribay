<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Monthly archive of top search queries per data source. A month is written once, after its data is final,
        // and kept; unlike search_queries it is never replaced by a later collection.
        Schema::create('search_query_months', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('source_id');
            $t->date('month');
            $t->date('start_date');
            $t->date('end_date');
            $t->unsignedTinyInteger('weeks')->nullable();
            $t->unsignedInteger('query_count');
            $t->timestamp('collected_at');
            $t->unique(['workspace_id', 'source_id', 'month']);
            $t->foreign(['workspace_id', 'source_id'])->references(['workspace_id', 'id'])->on('sources')->cascadeOnDelete();
        });
        Schema::create('search_query_month_rows', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('search_query_month_id')->constrained()->cascadeOnDelete();
            $t->text('query');
            $t->double('clicks');
            $t->double('impressions');
            $t->double('position')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_query_month_rows');
        Schema::dropIfExists('search_query_months');
    }
};
