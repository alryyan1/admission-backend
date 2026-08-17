<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFloorRequest;
use App\Http\Requests\UpdateFloorRequest;
use App\Models\Floor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class FloorController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Floor::withCount('wards')->orderBy('id')->get()
        );
    }

    public function store(StoreFloorRequest $request): JsonResponse
    {
        $floor = Floor::create($request->validated());

        return response()->json($floor, Response::HTTP_CREATED);
    }

    public function show(Floor $floor): JsonResponse
    {
        return response()->json($floor->load('wards.rooms.beds.currentAdmission.patient'));
    }

    public function update(UpdateFloorRequest $request, Floor $floor): JsonResponse
    {
        $floor->update($request->validated());

        return response()->json($floor);
    }

    public function destroy(Floor $floor): Response
    {
        $floor->delete();

        return response()->noContent();
    }
}
