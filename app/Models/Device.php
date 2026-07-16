<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\DeviceCategory;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'name', 'manufacturer', 'category', 'description'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
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
            'category' => DeviceCategory::class,
        ];
    }

    /**
     * The contracts this device type is attached to.
     *
     * @return BelongsToMany<Contract, $this, ContractDevice>
     */
    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(Contract::class)
            ->using(ContractDevice::class)
            ->withPivot('serial_number')
            ->withTimestamps();
    }

    /**
     * The physical units of this device type attached to contracts.
     *
     * @return HasMany<ContractDevice, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(ContractDevice::class);
    }
}
