<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWardRequest;
use App\Http\Requests\UpdateWardRequest;
use App\Models\Ward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Ward::withCount('rooms');

        if ($request->filled('floor_id')) {
            $query->where('floor_id', $request->integer('floor_id'));
        }

        return response()->json($query->orderBy('id')->get());
    }

    public function store(StoreWardRequest $request): JsonResponse
    {
        $ward = Ward::create($request->validated());

        return response()->json($ward, Response::HTTP_CREATED);
    }

    public function show(Ward $ward): JsonResponse
    {
        return response()->json($ward->load('rooms.beds.currentAdmission.patient'));
    }

    public function update(UpdateWardRequest $request, Ward $ward): JsonResponse
    {
        $ward->update($request->validated());

        return response()->json($ward);
    }

    public function destroy(Ward $ward): Response
    {
        $ward->delete();

        return response()->noContent();
    }
}
