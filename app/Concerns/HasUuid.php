<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Support\Str;

trait HasUuid
{
    /**
     * Assign a UUID to models created without one.
     */
    public static function bootHasUuid(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid7();
        });
    }

    /**
     * Route bind by UUID instead of the internal primary key.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
