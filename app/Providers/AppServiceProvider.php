<?php

namespace App\Providers;

use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Use multiple PHP workers locally so concurrent streaming requests are handled in parallel.
        if ($this->app->environment('local')) {
            DevCommands::register(
                'PHP_CLI_SERVER_WORKERS=4 php -d max_execution_time=0 -S 127.0.0.1:8000 -t public',
                'server'
            );
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
