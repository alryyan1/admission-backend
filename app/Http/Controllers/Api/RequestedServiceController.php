<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequestedServiceRequest;
use App\Http\Requests\UpdateRequestedServiceRequest;
use App\Models\Admission;
use App\Models\RequestedService;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class RequestedServiceController extends Controller
{
    public function index(Admission $admission): JsonResponse
    {
        return response()->json($admission->requestedServices()->latest()->get());
    }

    public function store(StoreRequestedServiceRequest $request, Admission $admission): JsonResponse
    {
        $admission->assertMutable($request->user());

        $service = $admission->requestedServices()->create([
            ...$request->validated(),
            'requested_by' => $request->user()->id,
        ]);

        return response()->json($service, Response::HTTP_CREATED);
    }

    public function update(
        UpdateRequestedServiceRequest $request,
        Admission $admission,
        RequestedService $requestedService,
    ): JsonResponse {
        abort_unless($requestedService->admission_id === $admission->id, Response::HTTP_NOT_FOUND);

        $admission->assertMutable($request->user());

        $requestedService->update($request->validated());

        return response()->json($requestedService->fresh());
    }

    public function destroy(Request $request, Admission $admission, RequestedService $requestedService): JsonResponse
    {
        abort_unless($requestedService->admission_id === $admission->id, Response::HTTP_NOT_FOUND);

        $admission->assertMutable($request->user());

        $requestedService->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function accommodationFee(Request $request, Admission $admission): JsonResponse
    {
        $admission->assertMutable($request->user());

        $room = $admission->bed->loadMissing('room')->room;

        if ($room->is_short_stay) {
            throw ValidationException::withMessages([
                'room' => ['احتساب رسوم الإقامة متاح فقط لغرف الإقامة العادية (غير القصيرة).'],
            ]);
        }

        if (! $room->price_per_day) {
            throw ValidationException::withMessages([
                'room' => ['لم يتم تحديد سعر اليوم لهذه الغرفة.'],
            ]);
        }

        $service = Service::firstOrCreate(
            ['name_ar' => 'رسوم الإقامة'],
            ['price' => $room->price_per_day, 'is_active' => true],
        );

        $requestedService = $admission->requestedServices()->create([
            'name' => $service->name_ar,
            'quantity' => $admission->nightsStayed(),
            'unit_price' => $room->price_per_day,
            'requested_by' => $request->user()->id,
        ]);

        return response()->json($requestedService, Response::HTTP_CREATED);
    }
}
