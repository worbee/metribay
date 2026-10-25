<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

// Prepare local runtime paths and secrets without ever printing key material.
$root = dirname(__DIR__).'/standalone';
foreach (['bootstrap/cache', 'storage/app/private', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $path) {
    if (! is_dir($root.'/'.$path)) {
        mkdir($root.'/'.$path, 0755, true);
    }
}
if (! is_file($root.'/.env')) {
    copy($root.'/.env.example', $root.'/.env');
    chmod($root.'/.env', 0600);
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! config('app.key')) {
    Artisan::call('key:generate', ['--force' => true]);
}
if (config('database.default') === 'sqlite') {
    $database = config('database.connections.sqlite.database');
    if (is_string($database) && $database !== ':memory:' && ! is_file($database)) {
        if (! is_dir(dirname($database))) {
            mkdir(dirname($database), 0700, true);
        }
        touch($database);
        chmod($database, 0600);
    }
}
echo "Runtime paths, environment, encryption key and local database are ready.\n";
