<?php

namespace XLaravel\Sluggable\Tests\Fixtures;

class RegeneratingPost extends Post
{
    protected $table = 'posts';

    protected bool $regenerateSlug = true;
}
