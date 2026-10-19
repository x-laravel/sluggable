<?php

namespace XLaravel\Sluggable\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use XLaravel\Sluggable\Support\SluggableConfig;

/**
 * @property int $id
 * @property string $sluggable_type
 * @property int|string $sluggable_id
 * @property string $slug
 * @property Carbon|null $created_at
 */
class SlugHistory extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = SluggableConfig::pruneAfter();

        return $days === null
            ? static::query()->whereRaw('1 = 0')
            : static::query()->where('created_at', '<', now()->subDays($days));
    }

    public function getTable(): string
    {
        return SluggableConfig::historyTable();
    }

    public function getConnectionName(): ?string
    {
        return SluggableConfig::connection() ?? parent::getConnectionName();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function sluggable(): MorphTo
    {
        return $this->morphTo();
    }
}
