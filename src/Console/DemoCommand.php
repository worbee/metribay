<?php

namespace Metribay\Core\Console;

use Illuminate\Console\Command;
use Metribay\Core\Services\DemoData;

class DemoCommand extends Command
{
    protected $signature = 'metribay:demo {--email=}';

    protected $description = 'Populate explicitly labeled synthetic demonstration data';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Demo data is available only outside production.');

            return self::FAILURE;
        }

        return app(DemoData::class)->seed($this, $this->option('email'));
    }
}
