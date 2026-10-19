<?php

namespace XLaravel\Sluggable\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Config;
use XLaravel\Sluggable\SlugGenerator;

class Slug implements ValidationRule
{
    public function __construct(private ?int $max = null) {}

    public static function make(): self
    {
        return new self;
    }

    public function max(int $max): self
    {
        $this->max = $max;

        return $this;
    }

    public static function passes(mixed $value): bool
    {
        $separator = preg_quote(SlugGenerator::separator(), '/');

        return is_string($value) && preg_match('/\A[a-z0-9]+(?:' . $separator . '[a-z0-9]+)*\z/', $value) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value)) {
            $fail('sluggable::validation.slug')->translate(['separator' => SlugGenerator::separator()]);

            return;
        }

        $max = $this->max ?? Config::integer('sluggable.max_length', 255);

        if (is_string($value) && strlen($value) > $max) {
            $fail('sluggable::validation.max')->translate(['max' => (string) $max]);
        }
    }
}
