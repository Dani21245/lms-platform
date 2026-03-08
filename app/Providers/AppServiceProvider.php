<?php

namespace App\Providers;

use App\Services\OtpService;
use App\Services\SmsService;
use App\Services\TelebirrService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OtpService::class);
        $this->app->singleton(SmsService::class);
        $this->app->singleton(TelebirrService::class);
    }

    public function boot(): void
    {
        //
    }
}
