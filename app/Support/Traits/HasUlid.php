<?php

namespace App\Support\Traits;

use Illuminate\Support\Str;

/**
 * Generates a ULID (Universally Unique Lexicographically Sortable Identifier)
 * on model creation. Use ulid instead of id in public-facing URLs.
 */
trait HasUlid
{
    protected static function bootHasUlid(): void
    {
        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = Str::ulid()->toBase32();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
