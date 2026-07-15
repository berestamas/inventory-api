<?php

declare(strict_types=1);

namespace App\Actions\Contracts;

use App\Data\Contracts\UpdateContractData;
use App\Models\Contract;

class UpdateContract
{
    /**
     * Update a contract; omitted (Optional) fields are left untouched.
     */
    public function handle(Contract $contract, UpdateContractData $updateContractData): Contract
    {
        $contract->update($updateContractData->toArray());

        return $contract;
    }
}
