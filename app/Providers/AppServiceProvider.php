<?php

namespace App\Providers;

use App\Services\DaemonClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // The full node the home page's statistics come from
        $this->app->singleton(DaemonClient::class, function () {
            return new DaemonClient(
                config('services.bitlitas.daemon_url'),
                config('services.bitlitas.daemon_login')
            );
        });
    }
}
