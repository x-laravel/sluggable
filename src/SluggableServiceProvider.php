<?php

namespace XLaravel\Sluggable;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use XLaravel\Sluggable\Rules\Slug;
use XLaravel\Sluggable\Support\SluggableConfig;

class SluggableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sluggable.php', 'sluggable');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'sluggable');

        Validator::extend('slug', fn (string $attribute, mixed $value) => Slug::passes($value));

        $this->callAfterResolving(Schedule::class, $this->schedulePruning(...));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/sluggable.php' => $this->app->configPath('sluggable.php'),
        ], 'sluggable-config');

        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'sluggable-migrations');

        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('vendor/sluggable'),
        ], 'sluggable-translations');
    }

    private function schedulePruning(Schedule $schedule): void
    {
        if (! Config::boolean('sluggable.prune.enabled', true)
            || ! Config::boolean('sluggable.history.enabled', true)
            || SluggableConfig::pruneAfter() === null) {
            return;
        }

        $frequency = Config::string('sluggable.prune.schedule', 'daily');

        $event = $schedule->command('model:prune', [
            '--model' => [SluggableConfig::historyModel()],
            '--chunk' => Config::integer('sluggable.prune.chunk', 1000),
        ]);

        if (str_contains($frequency, ' ')) {
            $event->cron($frequency);

            return;
        }

        [$method, $arguments] = array_pad(explode(':', $frequency, 2), 2, '');

        if ($method === '' || ! method_exists($event, $method)) {
            throw new InvalidArgumentException("[{$frequency}] is not a schedule frequency.");
        }

        $event->{$method}(...($arguments === '' ? [] : explode(',', $arguments)));
    }
}
