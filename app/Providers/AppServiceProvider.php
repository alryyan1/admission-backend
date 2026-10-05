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

        if (! defined('K_ALLOWED_PATHS')) {
            // TCPDF's file-access sandbox otherwise trusts local image reads
            // only under getcwd() and a few other candidates — which cover
            // storage/app/public under the CLI's cwd (the project root) but
            // not under the web server's, silently dropping facility
            // logo/watermark/stamp images on real HTTP requests. The whole
            // storage tree (not just app/public) also covers the faked disk
            // tests write to.
            define('K_ALLOWED_PATHS', [storage_path()]);
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
