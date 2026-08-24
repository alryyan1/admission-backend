<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequestedServiceRequest;
use App\Models\Admission;
use App\Models\RequestedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    public function destroy(Request $request, Admission $admission, RequestedService $requestedService): JsonResponse
    {
        abort_unless($requestedService->admission_id === $admission->id, Response::HTTP_NOT_FOUND);

        $admission->assertMutable($request->user());

        $requestedService->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
