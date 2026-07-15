<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Contracts\AttachDeviceToContract;
use App\Actions\Contracts\DetachDeviceFromContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttachDeviceRequest;
use App\Http\Resources\ContractDeviceUnitResource;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ContractDeviceController extends Controller
{
    /**
     * Attach a device unit to the contract.
     */
    public function store(AttachDeviceRequest $attachDeviceRequest, Contract $contract, AttachDeviceToContract $attachDeviceToContract): JsonResponse
    {
        $contractDevice = $attachDeviceToContract->handle($contract, $attachDeviceRequest->getData());

        return ContractDeviceUnitResource::make($contractDevice->load('device'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Detach the unit with the given serial number from the contract.
     */
    public function destroy(Contract $contract, string $serialNumber, DetachDeviceFromContract $detachDeviceFromContract): Response
    {
        $detachDeviceFromContract->handle($contract, $serialNumber);

        return response()->noContent();
    }
}
