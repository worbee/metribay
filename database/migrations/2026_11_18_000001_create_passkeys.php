<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Passkeys (WebAuthn credentials) used by Fortify's passkey sign-in; same shape as laravel/passkeys' own table. */
return new class extends Migration
{
    public function up(): void
    {
        // A host that already published the laravel/passkeys migration keeps its table.
        if (Schema::hasTable('passkeys')) {
            return;
        }
        Schema::create('passkeys', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('credential_id')->unique();
            $t->json('credential');
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
