<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('metribay:sync --except=uptimerobot')->cron('0 */4 * * *')->withoutOverlapping(240);
Schedule::command('metribay:sync --kind=uptimerobot')->hourlyAt(5)->withoutOverlapping(60);
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('metribay:sync --full --except=uptimerobot')->weeklyOn(0, '22:10')->withoutOverlapping(240);
