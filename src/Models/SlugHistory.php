<?php

namespace XLaravel\Sluggable\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Config;

/**
 * @property int $id
 * @property string $sluggable_type
 * @property int $sluggable_id
 * @property string $slug
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class SlugHistory extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    protected $guarded = [];

    public function getTable(): string
    {
        return Config::string('sluggable.history.table', 'slug_history');
    }

    public function getConnectionName(): ?string
    {
        $connection = Config::get('sluggable.history.connection');

        return is_string($connection) ? $connection : parent::getConnectionName();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function sluggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = Config::get('sluggable.history.prune_after');

        return is_int($days)
            ? static::query()->where('created_at', '<', now()->subDays($days))
            : static::query()->whereRaw('1 = 0');
    }
}
