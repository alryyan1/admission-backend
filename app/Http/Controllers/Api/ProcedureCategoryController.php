<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcedureCategoryRequest;
use App\Http\Requests\UpdateProcedureCategoryRequest;
use App\Models\ProcedureCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProcedureCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ProcedureCategory::orderBy('name')->get());
    }

    public function store(StoreProcedureCategoryRequest $request): JsonResponse
    {
        $category = ProcedureCategory::create($request->validated());

        return response()->json($category, Response::HTTP_CREATED);
    }

    public function update(UpdateProcedureCategoryRequest $request, ProcedureCategory $procedureCategory): JsonResponse
    {
        $procedureCategory->update($request->validated());

        return response()->json($procedureCategory);
    }

    public function destroy(ProcedureCategory $procedureCategory): JsonResponse
    {
        $procedureCategory->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
