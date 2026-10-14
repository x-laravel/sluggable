<?php

namespace XLaravel\Sluggable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use XLaravel\Sluggable\Concerns\HasSlug;
use XLaravel\Sluggable\Contracts\Sluggable;

/**
 * @property int $id
 * @property string|null $title
 * @property string|null $subtitle
 * @property string $slug
 */
class Post extends Model implements Sluggable
{
    use HasSlug;
    use SoftDeletes;

    public $timestamps = false;

    protected $guarded = [];

    /** @var string|array<int, string> */
    protected string|array $slugSource = 'title';

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
