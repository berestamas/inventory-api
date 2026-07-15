<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Contracts\CreateContract;
use App\Actions\Contracts\DeleteContract;
use App\Actions\Contracts\UpdateContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ContractController extends Controller
{
    /**
     * List contracts without their devices, with client-driven filtering and sorting.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $lengthAwarePaginator = QueryBuilder::for(Contract::query())
            ->allowedFilters(
                AllowedFilter::partial('contract_number'),
                AllowedFilter::partial('partner_name'),
            )
            ->allowedSorts('contract_number', 'signed_at', 'created_at')
            ->defaultSort('-created_at')
            ->paginate(25)
            ->appends($request->query());

        return ContractResource::collection($lengthAwarePaginator);
    }

    /**
     * Create a contract.
     */
    public function store(StoreContractRequest $storeContractRequest, CreateContract $createContract): JsonResponse
    {
        $contract = $createContract->handle($storeContractRequest->getData());

        return ContractResource::make($contract)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a single contract, including its attached device units.
     */
    public function show(Contract $contract): ContractResource
    {
        return ContractResource::make($contract->load('units.device'));
    }

    /**
     * Update a contract.
     */
    public function update(UpdateContractRequest $updateContractRequest, Contract $contract, UpdateContract $updateContract): ContractResource
    {
        return ContractResource::make($updateContract->handle($contract, $updateContractRequest->getData()));
    }

    /**
     * Delete a contract together with its attached units.
     */
    public function destroy(Contract $contract, DeleteContract $deleteContract): Response
    {
        $deleteContract->handle($contract);

        return response()->noContent();
    }
}
