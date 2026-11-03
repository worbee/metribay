<?php

namespace Metribay\Core\Console;

use Illuminate\Console\Command;
use Metribay\Core\Demo\SamplePortfolio;
use Metribay\Core\Services\DemoData;

class DemoCommand extends Command
{
    protected $signature = 'metribay:demo {--email= : Add the samples to this owner\'s workspace}
        {--fresh : Rebuild a dedicated demo SQLite database (file name ending in demo.sqlite) with its own sample owner}
        {--seed='.SamplePortfolio::SEED.' : Same seed, same data}
        {--days='.SamplePortfolio::DAYS.' : Days of history up to today}';

    protected $description = 'Populate explicitly labeled synthetic demonstration data';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Demo data is available only outside production.');

            return self::FAILURE;
        }
        $days = (int) $this->option('days');
        if ($days < 30 || $days > 1000) {
            $this->error('Use between 30 and 1000 days of history.');

            return self::FAILURE;
        }

        return app(DemoData::class)->seed($this, $this->option('email'), (bool) $this->option('fresh'), (int) $this->option('seed'), $days);
    }
}
