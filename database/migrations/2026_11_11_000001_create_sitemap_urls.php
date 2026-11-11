<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $t): void {
            $t->timestamp('sitemap_baselined_at')->nullable();
        });
        Schema::create('sitemap_urls', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('source_id');
            $t->char('url_hash', 64);
            $t->text('url');
            $t->timestamp('first_seen_at');
            $t->timestamp('last_seen_at');
            $t->timestamp('last_modified_at')->nullable();
            $t->boolean('is_baseline');
            $t->unique(['workspace_id', 'source_id', 'url_hash']);
            $t->index(['workspace_id', 'source_id', 'first_seen_at']);
            $t->foreign(['workspace_id', 'source_id'])->references(['workspace_id', 'id'])->on('sources')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitemap_urls');
        Schema::table('sources', function (Blueprint $t): void {
            $t->dropColumn('sitemap_baselined_at');
        });
    }
};
