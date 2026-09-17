<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('streams:fetch')->everyThirtyMinutes()->withoutOverlapping();
        // Between full runs, poll only channels whose manual schedule says they
        // should be live about now (:00 and :30 are covered by the full run).
        $schedule->command('streams:fetch --watched')->cron('10,20,40,50 * * * *')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
