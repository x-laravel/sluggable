<?php

namespace XLaravel\Sluggable\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use XLaravel\Sluggable\Concerns\HasSlug;

class UniqueSlug implements ValidationRule
{
    /**
     * @param  class-string<Model>|Model  $model
     */
    public function __construct(private string|Model $model) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $model = is_string($this->model) ? new $this->model : $this->model;

        if (! in_array(HasSlug::class, class_uses_recursive($model), true) || ! method_exists($model, 'slugTaken')) {
            throw new LogicException(sprintf('[%s] does not use the HasSlug trait.', $model::class));
        }

        if ($model->slugTaken(mb_strtolower($value))) {
            $fail('sluggable::validation.taken')->translate();
        }
    }
}
