<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequestedServiceRequest;
use App\Models\Admission;
use Illuminate\Http\JsonResponse;
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
}
