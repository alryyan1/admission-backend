<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTreatmentDoseRequest;
use App\Models\DoctorOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TreatmentDoseController extends Controller
{
    public function store(StoreTreatmentDoseRequest $request, DoctorOrder $order): JsonResponse
    {
        $order->admission->assertMutable($request->user());

        $dose = $order->doses()->create([
            ...$request->validated(),
            'administered_by' => $request->user()->id,
        ]);

        return response()->json($dose, Response::HTTP_CREATED);
    }
}
