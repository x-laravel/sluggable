<?php

namespace XLaravel\Sluggable\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;

class SlugChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly Model $model,
        public readonly string $old,
        public readonly string $new,
    ) {}
}
