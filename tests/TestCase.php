<?php

namespace XLaravel\Sluggable\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use XLaravel\Sluggable\SluggableServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [SluggableServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $connection = getenv('DB_CONNECTION');

        $app['config']->set('database.default', is_string($connection) && $connection !== '' ? $connection : 'testing');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require __DIR__ . '/../database/migrations/2026_10_14_000000_create_slug_history_table.php';

        if (is_object($migration) && method_exists($migration, 'down') && method_exists($migration, 'up')) {
            $migration->down();
            $migration->up();
        }

        Schema::dropIfExists('posts');
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('slug')->unique();
            $table->softDeletes();
        });
    }
}
