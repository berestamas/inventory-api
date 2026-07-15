<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Contracts\UpdateContractData;
use App\Models\Contract;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\WithData;

class UpdateContractRequest extends FormRequest
{
    /** @use WithData<UpdateContractData> */
    use WithData;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contract_number' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('contracts')->ignore($this->route('contract')),
            ],
            'partner_name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'signed_at' => ['sometimes', 'nullable', 'date'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * Validate the effective date range, falling back to stored values for omitted fields.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $contract = $this->route('contract');

                if (! $contract instanceof Contract) {
                    return;
                }

                $startsAt = $this->has('starts_at') ? $this->date('starts_at') : $contract->starts_at;
                $endsAt = $this->has('ends_at') ? $this->date('ends_at') : $contract->ends_at;

                if ($startsAt !== null && $endsAt !== null && $endsAt->lt($startsAt)) {
                    $validator->errors()->add('ends_at', 'The ends at date must be a date after or equal to starts at.');
                }
            },
        ];
    }

    /**
     * The data object resolved from the validated input.
     *
     * @return class-string<UpdateContractData>
     */
    protected function dataClass(): string
    {
        return UpdateContractData::class;
    }
}
