<?php

namespace Metribay\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Metribay\Core\Services\WorkspaceProvisioner;

class InstallCommand extends Command
{
    protected $signature = 'metribay:install {--email=} {--name=Metribay owner} {--password-file=}';

    protected $description = 'Migrate the database and create a verified first owner without printing credentials';

    public function handle(): int
    {
        $this->call('migrate', ['--force' => true]);
        $model = config('auth.providers.users.model');
        $email = $this->option('email') ?? $this->ask('Owner email');
        if ($model::query()->where('email', $email)->exists()) {
            $this->error('That account already exists. Use the password recovery flow.');

            return self::FAILURE;
        }
        $file = $this->option('password-file');
        $password = $file ? trim(file_get_contents($file)) : $this->secret('Password (at least 14 characters)');
        Validator::make(['email' => $email, 'password' => $password], ['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:14']])->validate();
        DB::transaction(function () use ($model, $email, $password): void {
            $user = $model::create(['name' => $this->option('name'), 'email' => mb_strtolower($email), 'password' => $password, 'email_verified_at' => now()]);
            app(WorkspaceProvisioner::class)->create($user, 'My workspace');
        });
        $this->info('Owner and workspace created. Sign in at '.config('app.url').'/login');

        return self::SUCCESS;
    }
}
