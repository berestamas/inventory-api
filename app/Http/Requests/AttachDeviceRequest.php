<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Contracts\AttachDeviceData;
use App\Models\ContractDevice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\WithData;

class AttachDeviceRequest extends FormRequest
{
    /** @use WithData<AttachDeviceData> */
    use WithData;

    /**
     * Canonicalize the serial before the unique rule runs, so case variants
     * collide identically on every database engine.
     */
    #[\Override]
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('serial_number'))) {
            $this->merge(['serial_number' => ContractDevice::canonicalSerial($this->string('serial_number')->value())]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device' => ['required', 'uuid', Rule::exists('devices', 'uuid')],
            'serial_number' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9._:-]+$/',
                Rule::unique('contract_device', 'serial_number'),
            ],
        ];
    }

    /**
     * The data object resolved from the validated input.
     *
     * @return class-string<AttachDeviceData>
     */
    protected function dataClass(): string
    {
        return AttachDeviceData::class;
    }
}
