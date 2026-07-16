<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApiRequestLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['method', 'path', 'query', 'request_headers', 'request_body', 'status', 'response_body', 'duration_ms', 'ip'])]
class ApiRequestLog extends Model
{
    /** @use HasFactory<ApiRequestLogFactory> */
    use HasFactory;

    use MassPrunable;

    /**
     * Log rows are immutable; only a creation timestamp is stored.
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'query' => 'array',
            'request_headers' => 'array',
        ];
    }

    /**
     * Logs older than 30 days are removed by the scheduled model:prune run.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(30));
    }
}
