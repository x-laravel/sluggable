<?php

namespace XLaravel\Sluggable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use XLaravel\Sluggable\Concerns\Sluggable;
use XLaravel\Sluggable\Contracts\HasSlug;

/**
 * @property int $id
 * @property string|null $title
 * @property string|null $subtitle
 * @property string $slug
 */
class Post extends Model implements HasSlug
{
    use Sluggable;
    use SoftDeletes;

    public $timestamps = false;

    protected $guarded = [];

    public function slugSource(): string|array
    {
        return 'title';
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
