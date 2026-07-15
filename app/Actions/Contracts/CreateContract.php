<?php

declare(strict_types=1);

namespace App\Actions\Contracts;

use App\Data\Contracts\CreateContractData;
use App\Models\Contract;

class CreateContract
{
    /**
     * Create a new contract.
     */
    public function handle(CreateContractData $createContractData): Contract
    {
        return Contract::create([
            'contract_number' => $createContractData->contractNumber,
            'partner_name' => $createContractData->partnerName,
            'description' => $createContractData->description,
            'signed_at' => $createContractData->signedAt,
            'starts_at' => $createContractData->startsAt,
            'ends_at' => $createContractData->endsAt,
        ]);
    }
}
