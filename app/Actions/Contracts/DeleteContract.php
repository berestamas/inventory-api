<?php

declare(strict_types=1);

namespace App\Actions\Contracts;

use App\Models\Contract;

class DeleteContract
{
    /**
     * Delete a contract; its attached units are removed by the database cascade.
     */
    public function handle(Contract $contract): void
    {
        $contract->delete();
    }
}
