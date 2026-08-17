<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDoctorOrderRequest;
use App\Models\Admission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DoctorOrderController extends Controller
{
    public function index(Admission $admission): JsonResponse
    {
        return response()->json($admission->doctorOrders()->with('orderedBy', 'doses')->latest()->get());
    }

    public function store(StoreDoctorOrderRequest $request, Admission $admission): JsonResponse
    {
        $admission->assertMutable($request->user());

        $order = $admission->doctorOrders()->create([
            ...$request->validated(),
            'ordered_by' => $request->user()->id,
        ]);

        return response()->json($order, Response::HTTP_CREATED);
    }
}
