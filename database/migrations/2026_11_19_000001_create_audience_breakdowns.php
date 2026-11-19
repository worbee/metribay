<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Visits per acquisition channel and day. Visits add up across days and channels, so any period can be summed.
        Schema::create('channel_days', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('source_id');
            $t->date('date');
            $t->string('channel', 32);
            $t->double('visits');
            $t->boolean('is_partial')->default(false);
            $t->timestamp('collected_at');
            $t->unique(['source_id', 'date', 'channel']);
            $t->index(['workspace_id', 'source_id', 'date']);
            $t->foreign(['workspace_id', 'source_id'])->references(['workspace_id', 'id'])->on('sources')->cascadeOnDelete();
        });

        // Top items of one dimension (referrers, landing pages, campaigns, countries, devices, goals) per reporting
        // window, replaced per dimension on every collection. Visitors per row are the provider's own count and never add up.
        Schema::create('breakdown_rows', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('source_id');
            $t->string('dimension', 32);
            $t->unsignedSmallInteger('days');
            $t->boolean('previous')->default(false);
            $t->date('start_date');
            $t->date('end_date');
            $t->text('item');
            $t->double('visits')->nullable();
            $t->double('visitors')->nullable();
            $t->double('conversions')->nullable();
            $t->boolean('is_partial')->default(false);
            $t->timestamp('collected_at');
            $t->index(['workspace_id', 'source_id', 'dimension', 'days']);
            $t->foreign(['workspace_id', 'source_id'])->references(['workspace_id', 'id'])->on('sources')->cascadeOnDelete();
        });

        // When the top lists were last read, so they refresh about once a day even when a site has nothing to list.
        Schema::table('sources', function (Blueprint $t): void {
            $t->timestamp('breakdowns_collected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $t): void {
            $t->dropColumn('breakdowns_collected_at');
        });
        Schema::dropIfExists('breakdown_rows');
        Schema::dropIfExists('channel_days');
    }
};
