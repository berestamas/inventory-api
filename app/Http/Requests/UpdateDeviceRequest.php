<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Devices\UpdateDeviceData;
use App\Enums\DeviceCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\WithData;

class UpdateDeviceRequest extends FormRequest
{
    /** @use WithData<UpdateDeviceData> */
    use WithData;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'manufacturer' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', Rule::enum(DeviceCategory::class)],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * The data object resolved from the validated input.
     *
     * @return class-string<UpdateDeviceData>
     */
    protected function dataClass(): string
    {
        return UpdateDeviceData::class;
    }
}
