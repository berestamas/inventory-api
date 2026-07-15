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
        $contract = new Contract($createContractData->forSaving());
        $contract->saveOrFail();

        return $contract;
    }
}
