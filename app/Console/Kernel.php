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
        // Clean expired OTP codes daily
        $schedule->command('model:prune', ['--model' => 'App\\Models\\OtpCode'])->daily();

        // Process any stuck pending payments
        $schedule->call(function () {
            \App\Models\Payment::where('status', 'pending')
                ->where('created_at', '<', now()->subHours(2))
                ->update(['status' => 'failed']);
        })->hourly();
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
