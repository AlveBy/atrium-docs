<?php

declare(strict_types=1);

namespace Atrium\Docs;

use Atrium\Docs\Console\PullCommand;
use Atrium\Docs\Http\Controllers\DocsController;
use Atrium\Theme\ThemeServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class DocsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/atrium-docs.php', 'atrium-docs');
        $this->app->singleton(Docs::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'atrium-docs');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'atrium-docs');

        $this->app->booted(fn () => $this->registerRoutes());

        if ($this->app->runningInConsole()) {
            $this->commands([PullCommand::class]);

            $this->publishes([
                __DIR__.'/../config/atrium-docs.php' => config_path('atrium-docs.php'),
            ], 'atrium-docs-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/atrium-docs'),
            ], 'atrium-docs-views');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/atrium-docs'),
            ], 'atrium-docs-lang');
        }
    }

    /**
     * The layout docs pages extend: the configured one, the theme's when
     * the theme is installed, else the package's own.
     */
    public static function layout(): string
    {
        $configured = config('atrium-docs.layout');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return app()->providerIsLoaded(ThemeServiceProvider::class) ? 'atrium-theme::layout' : 'atrium-docs::layout';
    }

    /**
     * The stylesheet goes inline, like the theme's: one request less.
     */
    public static function styles(): string
    {
        static $styles = null;

        return $styles ??= (string) file_get_contents(__DIR__.'/../resources/css/docs.css');
    }

    private function registerRoutes(): void
    {
        if (! config('atrium-docs.routes', true) || $this->app->routesAreCached()) {
            return;
        }

        $prefix = trim((string) config('atrium-docs.prefix', 'docs'), '/');
        $version = '[A-Za-z0-9][A-Za-z0-9._-]{0,63}';

        // Pictures are the same in every language: one address for them.
        Route::get($prefix.'/{version}/_assets/{path}', [DocsController::class, 'asset'])
            ->where(['version' => $version, 'path' => '.+'])
            ->name('atrium.docs.asset');

        Route::middleware('web')->group(function () use ($prefix, $version): void {
            Route::localized(function () use ($prefix, $version): void {
                Route::get($prefix, [DocsController::class, 'index'])->name('atrium.docs.index');

                Route::get($prefix.'/{version}/search', [DocsController::class, 'search'])
                    ->where('version', $version)
                    ->name('atrium.docs.search');

                Route::get($prefix.'/{version}/{page?}', [DocsController::class, 'show'])
                    ->where(['version' => $version, 'page' => '[A-Za-z0-9/_-]*'])
                    ->name('atrium.docs.show');
            });
        });
    }
}
