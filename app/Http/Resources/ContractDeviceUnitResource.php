<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ContractDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContractDevice
 */
class ContractDeviceUnitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'serial_number' => $this->serial_number,
            'attached_at' => $this->created_at,
            'device' => DeviceResource::make($this->whenLoaded('device')),
        ];
    }
}
