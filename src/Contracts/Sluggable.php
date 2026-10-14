<?php

namespace XLaravel\Sluggable\Contracts;

interface Sluggable
{
    public function slugColumn(): string;

    public function slugSource(): string;
}
