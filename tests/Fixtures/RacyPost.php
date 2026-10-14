<?php

namespace XLaravel\Sluggable\Tests\Fixtures;

class RacyPost extends Post
{
    public static int $misses = 0;

    protected $table = 'posts';

    public function slugTaken(string $slug): bool
    {
        if (self::$misses > 0) {
            self::$misses--;

            return false;
        }

        return parent::slugTaken($slug);
    }
}
