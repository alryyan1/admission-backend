<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVitalSignRequest;
use App\Models\Admission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class VitalSignController extends Controller
{
    public function index(Admission $admission): JsonResponse
    {
        return response()->json($admission->vitalSigns()->latest('recorded_at')->get());
    }

    public function store(StoreVitalSignRequest $request, Admission $admission): JsonResponse
    {
        $admission->assertMutable($request->user());

        $vitalSign = $admission->vitalSigns()->create([
            ...$request->validated(),
            'recorded_by' => $request->user()->id,
        ]);

        return response()->json($vitalSign, Response::HTTP_CREATED);
    }
}
