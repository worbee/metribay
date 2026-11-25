<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Metribay\Core\Services\ProviderCatalog;

return new class extends Migration
{
    /**
     * Before 1.7.2 a project moved without its shared accounts kept its data sources switched on, so every run failed
     * for lack of credentials. Those sources now pause, as they do when their account is removed; history stays.
     */
    public function up(): void
    {
        $kinds = DB::table('sources')->where('enabled', true)->whereNull('connection_id')->distinct()->pluck('kind')
            ->filter(fn (string $kind): bool => ProviderCatalog::requiresConnection($kind))->values()->all();
        if ($kinds === []) {
            return;
        }
        DB::table('sources')->where('enabled', true)->whereNull('connection_id')->whereIn('kind', $kinds)
            ->update(['enabled' => false, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
    }

    /** Paused sources are not switched back on: an account must be chosen first. */
    public function down(): void {}
};
