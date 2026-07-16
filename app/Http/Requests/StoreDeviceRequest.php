<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Devices\CreateDeviceData;
use App\Enums\DeviceCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\WithData;

class StoreDeviceRequest extends FormRequest
{
    /** @use WithData<CreateDeviceData> */
    use WithData;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'manufacturer' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(DeviceCategory::class)],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * The data object resolved from the validated input.
     *
     * @return class-string<CreateDeviceData>
     */
    protected function dataClass(): string
    {
        return CreateDeviceData::class;
    }
}
