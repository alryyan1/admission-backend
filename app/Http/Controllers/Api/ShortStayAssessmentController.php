<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShortStayAssessmentRequest;
use App\Models\Admission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ShortStayAssessmentController extends Controller
{
    public function show(Admission $admission): Response
    {
        $assessment = $admission->shortStayAssessment()->with('assessedBy')->first();

        // response()->json(null) serializes to "{}" (Symfony's JsonResponse
        // treats a null $data argument as an empty ArrayObject), which the
        // frontend would see as a truthy empty object instead of "no
        // assessment yet" — use a plain Response so a missing assessment is
        // a literal JSON null.
        return response(json_encode($assessment), 200, ['Content-Type' => 'application/json']);
    }

    public function store(StoreShortStayAssessmentRequest $request, Admission $admission): JsonResponse
    {
        $admission->assertMutable($request->user());

        $assessment = $admission->shortStayAssessment()->updateOrCreate(
            [],
            [
                ...$request->validated(),
                'assessed_by' => $request->user()->id,
                'signed_at' => now(),
            ]
        );

        return response()->json($assessment);
    }
}
