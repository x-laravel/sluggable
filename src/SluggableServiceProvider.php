<?php

namespace XLaravel\Sluggable;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use XLaravel\Sluggable\Rules\Slug;

class SluggableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sluggable.php', 'sluggable');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'sluggable');

        $this->publishes([
            __DIR__ . '/../config/sluggable.php' => $this->app->configPath('sluggable.php'),
        ], 'sluggable-config');

        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'sluggable-migrations');

        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('vendor/sluggable'),
        ], 'sluggable-translations');

        Validator::extend('slug', fn (string $attribute, mixed $value) => Slug::passes($value));
    }
}
