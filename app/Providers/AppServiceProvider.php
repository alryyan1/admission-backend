<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', rtrim((string) config('pdf.fonts_path'), '/\\').DIRECTORY_SEPARATOR);
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
