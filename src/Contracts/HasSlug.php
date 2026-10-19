<?php

namespace XLaravel\Sluggable\Contracts;

interface HasSlug
{
    /**
     * @return string|array<int, string>
     */
    public function slugSource(): string|array;
}
