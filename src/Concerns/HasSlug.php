<?php

namespace XLaravel\Sluggable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use LogicException;
use XLaravel\Sluggable\Events\SlugChanged;
use XLaravel\Sluggable\Models\SlugHistory;
use XLaravel\Sluggable\SlugGenerator;

/**
 * @mixin Model
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function (Model $model) {
            /** @var Model&self $model */
            $model->prepareSlug();

            if ($model->slugIsChanging() && $model->fireModelEvent('slugChanging') === false) {
                return false;
            }
        });

        static::created(function (Model $model) {
            /** @var Model&self $model */
            $model->releaseSlugFromHistory();
        });

        static::updated(function (Model $model) {
            /** @var Model&self $model */
            $model->recordSlugChange();
        });

        static::deleted(function (Model $model) {
            /** @var Model&self $model */
            $model->forgetSlugHistoryWhenGone();
        });
    }

    public function initializeHasSlug(): void
    {
        $this->addObservableEvents(['slugChanging', 'slugChanged']);
    }

    /**
     * @param  \Illuminate\Events\QueuedClosure|callable|array<int, mixed>|class-string  $callback
     */
    public static function slugChanging($callback): void
    {
        static::registerModelEvent('slugChanging', $callback);
    }

    /**
     * @param  \Illuminate\Events\QueuedClosure|callable|array<int, mixed>|class-string  $callback
     */
    public static function slugChanged($callback): void
    {
        static::registerModelEvent('slugChanged', $callback);
    }

    /**
     * @param  array{touch?: bool|null}  $options
     */
    public function save(array $options = []): bool
    {
        for ($attempt = 1; ; $attempt++) {
            $generating = $this->slugIsGenerated();

            try {
                return $this->getConnection()->transactionLevel() > 0
                    ? $this->getConnection()->transaction(fn () => parent::save($options))
                    : parent::save($options);
            } catch (UniqueConstraintViolationException $exception) {
                if (! $generating || $attempt >= Config::integer('sluggable.retries', 3) || ! str_contains($exception->getMessage(), $this->slugColumn())) {
                    throw $exception;
                }

                $this->setAttribute($this->slugColumn(), null);
            }
        }
    }

    public function slugColumn(): string
    {
        $column = property_exists($this, 'slugColumn') ? $this->slugColumn : null;

        return is_string($column) && $column !== '' ? $column : 'slug';
    }

    public function slugSource(): string
    {
        return implode(' ', array_map(function (string $attribute) {
            $value = $this->getAttribute($attribute);

            return is_scalar($value) ? (string) $value : '';
        }, $this->slugSourceAttributes()));
    }

    /**
     * @return array<int, string>
     */
    public function slugSourceAttributes(): array
    {
        $source = property_exists($this, 'slugSource') ? $this->slugSource : null;

        if (is_string($source) && $source !== '') {
            return [$source];
        }

        if (is_array($source) && $source !== []) {
            return array_values(array_filter($source, is_string(...)));
        }

        throw new LogicException(sprintf('Set a $slugSource property on [%s] or override slugSource().', static::class));
    }

    public function regeneratesSlug(): bool
    {
        $regenerate = property_exists($this, 'regenerateSlug') ? $this->regenerateSlug : null;

        return is_bool($regenerate) ? $regenerate : Config::boolean('sluggable.regenerate', false);
    }

    public function keepsSlugHistory(): bool
    {
        $history = property_exists($this, 'slugHistory') ? $this->slugHistory : null;

        return Config::boolean('sluggable.history.enabled', true) && $history !== false;
    }

    public function slugReservedFor(): ?int
    {
        if (property_exists($this, 'slugReservedFor')) {
            return is_int($this->slugReservedFor) ? $this->slugReservedFor : null;
        }

        $days = Config::get('sluggable.history.reserve_for');

        return is_int($days) ? $days : null;
    }

    public function slugMaxLength(): int
    {
        $length = property_exists($this, 'slugMaxLength') ? $this->slugMaxLength : null;

        return is_int($length) ? $length : Config::integer('sluggable.max_length', 255);
    }

    public function slugTaken(string $slug): bool
    {
        return $this->slugUniqueQuery()
            ->where($this->slugColumn(), $slug)
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->exists()
            || $this->slugIsReserved($slug);
    }

    public function slugIsReserved(string $slug): bool
    {
        return $this->reservedSlugs()?->where('slug', $slug)->exists() ?? false;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWhereSlug(Builder $query, string $slug): void
    {
        $this->constrainToSlug($query, $slug);
    }

    public static function findBySlug(string $slug): ?static
    {
        /** @var static|null */
        return static::query()->whereSlug($slug)->first();
    }

    /**
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if (($field ?? $this->getRouteKeyName()) !== $this->slugColumn() || ! is_string($value)) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        $builder = $query instanceof Model ? $query->newQuery() : $query;

        $this->constrainToSlug($builder, $value);

        return $builder;
    }

    /**
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     */
    protected function constrainToSlug($query, string $slug): void
    {
        $ids = $this->keepsSlugHistory()
            ? $this->slugHistory()->where('slug', $slug)->pluck('sluggable_id')->all()
            : [];

        $query->where(function (Builder $query) use ($slug, $ids) {
            $query->where($query->qualifyColumn($this->slugColumn()), $slug);

            if ($ids !== []) {
                $query->orWhereIn($query->qualifyColumn($this->getKeyName()), $ids);
            }
        });
    }

    /**
     * @return Builder<*>
     */
    protected function slugUniqueQuery(): Builder
    {
        return static::query()->withoutGlobalScopes();
    }

    /**
     * @return Builder<SlugHistory>
     */
    protected function slugHistory(): Builder
    {
        return SlugHistory::query()->where('sluggable_type', $this->getMorphClass());
    }

    /**
     * @return Builder<SlugHistory>|null
     */
    protected function reservedSlugs(): ?Builder
    {
        $days = $this->slugReservedFor();

        if (! $this->keepsSlugHistory() || $days === 0) {
            return null;
        }

        return $this->slugHistory()
            ->when($this->exists, fn (Builder $query) => $query->where('sluggable_id', '!=', $this->getKey()))
            ->when($days !== null, fn (Builder $query) => $query->where('created_at', '>=', now()->subDays((int) $days)));
    }

    protected function generateSlug(): string
    {
        $length = $this->slugMaxLength();
        $base = SlugGenerator::slugify($this->slugSource(), $length);
        $prefix = $base . SlugGenerator::separator();
        $taken = array_flip($this->slugsStartingWith($base));

        return SlugGenerator::unique(
            $base,
            fn (string $slug) => $slug === $base || str_starts_with($slug, $prefix) ? isset($taken[$slug]) : $this->slugTaken($slug),
            $length,
        );
    }

    /**
     * @return array<int, string>
     */
    protected function slugsStartingWith(string $base): array
    {
        $pattern = $base . SlugGenerator::separator() . '%';
        $matching = fn (Builder $query, string $column) => $query->where(
            fn (Builder $query) => $query->where($column, $base)->orWhere($column, 'like', $pattern),
        );

        $live = $matching($this->slugUniqueQuery(), $this->slugColumn())
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->pluck($this->slugColumn())
            ->all();

        $history = $this->reservedSlugs();
        $reserved = $history === null ? [] : $matching($history, 'slug')->pluck('slug')->all();

        return array_values(array_filter([...$live, ...$reserved], is_string(...)));
    }

    protected function prepareSlug(): void
    {
        $column = $this->slugColumn();
        $value = $this->getAttribute($column);

        if (! is_string($value) || trim($value) === '') {
            $this->setAttribute($column, $this->generateSlug());

            return;
        }

        if ($this->isDirty($column)) {
            $this->setAttribute($column, Str::lower(trim($value)));

            return;
        }

        if ($this->exists && $this->regeneratesSlug() && $this->isDirty($this->slugSourceAttributes())) {
            $this->setAttribute($column, $this->generateSlug());
        }
    }

    protected function slugIsGenerated(): bool
    {
        $value = $this->getAttribute($this->slugColumn());

        if (! is_string($value) || trim($value) === '') {
            return true;
        }

        return $this->exists
            && ! $this->isDirty($this->slugColumn())
            && $this->regeneratesSlug()
            && $this->isDirty($this->slugSourceAttributes());
    }

    protected function slugIsChanging(): bool
    {
        $old = $this->getRawOriginal($this->slugColumn());

        return $this->exists && $this->isDirty($this->slugColumn()) && is_string($old) && $old !== '';
    }

    protected function releaseSlugFromHistory(): void
    {
        $slug = $this->getAttribute($this->slugColumn());

        if ($this->keepsSlugHistory() && is_string($slug)) {
            $this->slugHistory()->where('slug', $slug)->delete();
        }
    }

    protected function recordSlugChange(): void
    {
        $column = $this->slugColumn();
        $old = $this->getRawOriginal($column);
        $new = $this->getAttribute($column);

        if (! $this->wasChanged($column) || ! is_string($new)) {
            return;
        }

        $this->releaseSlugFromHistory();

        if (! is_string($old) || $old === '') {
            return;
        }

        if ($this->keepsSlugHistory()) {
            $this->slugHistory()->where('slug', $old)->delete();

            SlugHistory::query()->create([
                'sluggable_type' => $this->getMorphClass(),
                'sluggable_id' => $this->getKey(),
                'slug' => $old,
                'created_at' => now(),
            ]);
        }

        $this->fireModelEvent('slugChanged', false);

        Event::dispatch(new SlugChanged($this, $old, $new));
    }

    protected function forgetSlugHistoryWhenGone(): void
    {
        if (method_exists($this, 'isForceDeleting') && ! $this->isForceDeleting()) {
            return;
        }

        SlugHistory::query()
            ->where('sluggable_type', $this->getMorphClass())
            ->where('sluggable_id', $this->getKey())
            ->delete();
    }
}
