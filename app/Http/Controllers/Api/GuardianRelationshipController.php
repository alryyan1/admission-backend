<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGuardianRelationshipRequest;
use App\Http\Requests\UpdateGuardianRelationshipRequest;
use App\Models\GuardianRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GuardianRelationshipController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(GuardianRelationship::orderBy('name')->get());
    }

    public function store(StoreGuardianRelationshipRequest $request): JsonResponse
    {
        $guardianRelationship = GuardianRelationship::create($request->validated());

        return response()->json($guardianRelationship, Response::HTTP_CREATED);
    }

    public function update(UpdateGuardianRelationshipRequest $request, GuardianRelationship $guardianRelationship): JsonResponse
    {
        $guardianRelationship->update($request->validated());

        return response()->json($guardianRelationship);
    }

    public function destroy(GuardianRelationship $guardianRelationship): JsonResponse
    {
        $guardianRelationship->patients()->update(['guardian_relationship_id' => null]);
        $guardianRelationship->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
