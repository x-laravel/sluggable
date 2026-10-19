<?php

namespace XLaravel\Sluggable\Support;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use XLaravel\Sluggable\Models\SlugHistory;

/**
 * @internal
 */
final class SluggableConfig
{
    /**
     * @return class-string<SlugHistory>
     */
    public static function historyModel(): string
    {
        $model = Config::get('sluggable.history.model') ?? SlugHistory::class;

        if (! is_string($model) || ! is_a($model, SlugHistory::class, true)) {
            throw new InvalidArgumentException('Set sluggable.history.model to a model that extends ' . SlugHistory::class . '.');
        }

        return $model;
    }

    public static function historyTable(): string
    {
        return self::string('history.table') ?: 'slug_history';
    }

    public static function connection(): ?string
    {
        return self::string('connection') ?: null;
    }

    public static function pruneAfter(): ?int
    {
        $days = Config::get('sluggable.prune.after', 365);

        return is_int($days) ? $days : null;
    }

    protected static function string(string $key): string
    {
        $value = Config::get("sluggable.{$key}");

        return is_string($value) ? trim($value) : '';
    }
}
