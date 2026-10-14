<?php

namespace XLaravel\Sluggable;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use XLaravel\Sluggable\Models\SlugHistory;
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

        $this->callAfterResolving(Schedule::class, $this->schedulePruning(...));
    }

    private function schedulePruning(Schedule $schedule): void
    {
        $frequency = Config::get('sluggable.history.prune_schedule', 'daily');

        if (! is_string($frequency) || $frequency === ''
            || ! Config::boolean('sluggable.history.enabled', true)
            || Config::get('sluggable.history.prune_after', 365) === null) {
            return;
        }

        $event = $schedule->command('model:prune', ['--model' => [SlugHistory::class]]);

        if (str_contains($frequency, ' ')) {
            $event->cron($frequency);

            return;
        }

        if (! method_exists($event, $frequency)) {
            throw new InvalidArgumentException("[{$frequency}] is not a schedule frequency.");
        }

        $event->{$frequency}();
    }
}
