<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Devices\CreateDevice;
use App\Actions\Devices\DeleteDevice;
use App\Actions\Devices\UpdateDevice;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DeviceController extends Controller
{
    /**
     * List devices with client-driven filtering, sorting, and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $lengthAwarePaginator = QueryBuilder::for(Device::query())
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::partial('manufacturer'),
                AllowedFilter::exact('category'),
            )
            ->allowedSorts('name', 'manufacturer', 'created_at')
            ->defaultSort('-created_at')
            ->paginate(25)
            ->appends($request->query());

        return DeviceResource::collection($lengthAwarePaginator);
    }

    /**
     * Create a device.
     */
    public function store(StoreDeviceRequest $storeDeviceRequest, CreateDevice $createDevice): JsonResponse
    {
        $device = $createDevice->handle($storeDeviceRequest->getData());

        return DeviceResource::make($device)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a single device.
     */
    public function show(Device $device): DeviceResource
    {
        return DeviceResource::make($device);
    }

    /**
     * Update a device.
     */
    public function update(UpdateDeviceRequest $updateDeviceRequest, Device $device, UpdateDevice $updateDevice): DeviceResource
    {
        return DeviceResource::make($updateDevice->handle($device, $updateDeviceRequest->getData()));
    }

    /**
     * Delete a device.
     */
    public function destroy(Device $device, DeleteDevice $deleteDevice): Response
    {
        $deleteDevice->handle($device);

        return response()->noContent();
    }
}
