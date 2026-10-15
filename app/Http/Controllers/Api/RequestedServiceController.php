<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayRequestedServiceRequest;
use App\Http\Requests\StoreRequestedServiceRequest;
use App\Http\Requests\UpdateRequestedServiceRequest;
use App\Models\Admission;
use App\Models\RequestedService;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestedServiceController extends Controller
{
    public function index(Admission $admission): JsonResponse
    {
        return response()->json($admission->requestedServices()->with(['paymentMethod', 'deposits'])->latest()->get());
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

        return response()->json($requestedService->fresh(['paymentMethod', 'deposits']));
    }

    /**
     * Marks a service line as paid and records the matching deposit in one transaction,
     * linking the two so cancelPayment() can find and remove the deposit again.
     */
    public function pay(
        PayRequestedServiceRequest $request,
        Admission $admission,
        RequestedService $requestedService,
    ): JsonResponse {
        abort_unless($requestedService->admission_id === $admission->id, Response::HTTP_NOT_FOUND);

        $admission->assertMutable($request->user());

        if ($requestedService->deposits()->exists()) {
            throw ValidationException::withMessages([
                'is_paid' => ['هذه الخدمة مدفوعة بالفعل.'],
            ]);
        }

        $paymentMethodId = $request->integer('payment_method_id') ?: null;

        DB::transaction(function () use ($admission, $requestedService, $paymentMethodId, $request) {
            $admission->deposits()->create([
                'requested_service_id' => $requestedService->id,
                'amount' => $requestedService->total_price,
                'payment_method_id' => $paymentMethodId,
                'comment' => 'سداد: '.$requestedService->name,
                'paid_by' => $request->user()->id,
            ]);

            $requestedService->update(['payment_method_id' => $paymentMethodId]);
        });

        return response()->json($requestedService->fresh(['paymentMethod', 'deposits']));
    }

    /**
     * Reverts pay(): deletes the deposit(s) it created for this service and clears the paid flag.
     */
    public function cancelPayment(Request $request, Admission $admission, RequestedService $requestedService): JsonResponse
    {
        abort_unless($requestedService->admission_id === $admission->id, Response::HTTP_NOT_FOUND);

        $admission->assertMutable($request->user());

        DB::transaction(function () use ($requestedService) {
            $requestedService->deposits()->delete();
            $requestedService->update(['payment_method_id' => null]);
        });

        return response()->json($requestedService->fresh(['paymentMethod', 'deposits']));
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

        if ($admission->bed === null) {
            throw ValidationException::withMessages([
                'bed_id' => ['لا يمكن إضافة رسوم الإقامة قبل تعيين سرير للتنويم.'],
            ]);
        }

        $room = $admission->bed->loadMissing('room')->room;

        if (! $room->price_per_day) {
            throw ValidationException::withMessages([
                'room' => ['لم يتم تحديد سعر اليوم لهذه الغرفة.'],
            ]);
        }

        $service = Service::firstOrCreate(
            ['name_ar' => RequestedService::AccommodationFeeName],
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
