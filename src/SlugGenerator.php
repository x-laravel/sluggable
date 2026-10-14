<?php

namespace XLaravel\Sluggable;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

final class SlugGenerator
{
    public static function slugify(string $value, ?int $maxLength = null): string
    {
        $separator = self::separator();
        $slug = Str::slug(Str::ascii($value, Config::string('sluggable.language', 'en')), $separator, 'en');

        return self::cut($slug, $maxLength ?? Config::integer('sluggable.max_length', 255));
    }

    /**
     * @param  callable(string): bool  $taken
     */
    public static function unique(string $slug, callable $taken, ?int $maxLength = null): string
    {
        $maxLength ??= Config::integer('sluggable.max_length', 255);
        $separator = self::separator();
        $candidate = $slug;

        for ($suffix = Config::integer('sluggable.first_suffix', 2); $candidate === '' || $taken($candidate); $suffix++) {
            $ending = $separator . $suffix;
            $base = self::cut($slug, $maxLength - strlen($ending));
            $candidate = $base === '' ? (string) $suffix : $base . $ending;
        }

        return $candidate;
    }

    public static function separator(): string
    {
        return Config::string('sluggable.separator', '-');
    }

    private static function cut(string $slug, int $length): string
    {
        return rtrim(substr($slug, 0, max(0, $length)), self::separator());
    }
}
