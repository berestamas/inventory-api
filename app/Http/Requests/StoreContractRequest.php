<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Contracts\CreateContractData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\LaravelData\WithData;

class StoreContractRequest extends FormRequest
{
    /** @use WithData<CreateContractData> */
    use WithData;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contract_number' => ['required', 'string', 'max:255', 'unique:contracts'],
            'partner_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'signed_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date', 'required_with:ends_at'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    /**
     * The data object resolved from the validated input.
     *
     * @return class-string<CreateContractData>
     */
    protected function dataClass(): string
    {
        return CreateContractData::class;
    }
}
