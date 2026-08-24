<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamRoleRequest;
use App\Http\Requests\UpdateTeamRoleRequest;
use App\Models\TeamRole;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class TeamRoleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TeamRole::query()->orderBy('sort_order')->orderBy('id')->get());
    }

    public function store(StoreTeamRoleRequest $request): JsonResponse
    {
        $role = TeamRole::create($request->validated());

        return response()->json($role, Response::HTTP_CREATED);
    }

    public function update(UpdateTeamRoleRequest $request, TeamRole $teamRole): JsonResponse
    {
        $teamRole->update($request->validated());

        return response()->json($teamRole);
    }

    public function destroy(TeamRole $teamRole): JsonResponse
    {
        if ($teamRole->is_protected) {
            throw ValidationException::withMessages([
                'team_role' => ['لا يمكن حذف هذا الدور لأنه دور أساسي في النظام.'],
            ]);
        }

        try {
            $teamRole->delete();
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'team_role' => ['لا يمكن حذف هذا الدور لارتباطه بسجلات مرتبطة.'],
            ]);
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
