<?php

namespace XLaravel\Sluggable\Tests\Fixtures;

class RacyPost extends Post
{
    public static int $misses = 0;

    protected $table = 'posts';

    protected function slugsStartingWith(string $base): array
    {
        if (self::$misses > 0) {
            self::$misses--;

            return [];
        }

        return parent::slugsStartingWith($base);
    }
}
