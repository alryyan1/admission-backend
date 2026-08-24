<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSpecialistRequest;
use App\Http\Requests\UpdateSpecialistRequest;
use App\Models\Specialist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SpecialistController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Specialist::orderBy('name')->get());
    }

    public function store(StoreSpecialistRequest $request): JsonResponse
    {
        $specialist = Specialist::create($request->validated());

        return response()->json($specialist, Response::HTTP_CREATED);
    }

    public function update(UpdateSpecialistRequest $request, Specialist $specialist): JsonResponse
    {
        $specialist->update($request->validated());

        return response()->json($specialist);
    }

    public function destroy(Specialist $specialist): JsonResponse
    {
        $specialist->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
