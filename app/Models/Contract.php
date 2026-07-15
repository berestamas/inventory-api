<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $signed_at
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
#[Fillable(['uuid', 'contract_number', 'partner_name', 'description', 'signed_at', 'starts_at', 'ends_at'])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    use HasUuid;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'signed_at' => 'date',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    /**
     * The device types attached to this contract.
     *
     * @return BelongsToMany<Device, $this, ContractDevice>
     */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class)
            ->using(ContractDevice::class)
            ->withPivot('serial_number')
            ->withTimestamps();
    }

    /**
     * The physical device units attached to this contract.
     *
     * @return HasMany<ContractDevice, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(ContractDevice::class);
    }
}
