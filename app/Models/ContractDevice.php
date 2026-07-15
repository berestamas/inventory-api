<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ContractDevice extends Pivot
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    #[\Override]
    public $incrementing = true;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    #[\Override]
    protected $table = 'contract_device';

    /**
     * Canonical storage form of a serial number: uppercase, so uniqueness
     * behaves identically on case-insensitive (MySQL) and case-sensitive
     * (SQLite) engines. Both validation and persistence must use this form.
     */
    public static function canonicalSerial(string $serialNumber): string
    {
        return mb_strtoupper($serialNumber);
    }

    /**
     * The contract this unit belongs to.
     *
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * The device type of this unit.
     *
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
