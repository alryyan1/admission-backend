<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcedureRequest;
use App\Http\Requests\UpdateProcedureRequest;
use App\Models\Procedure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ProcedureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Procedure::with('category');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name_ar', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        return response()->json($query->orderBy('name_ar')->get());
    }

    public function store(StoreProcedureRequest $request): JsonResponse
    {
        $procedure = Procedure::create($request->validated());
        $procedure->load('category');

        return response()->json($procedure, Response::HTTP_CREATED);
    }

    public function update(UpdateProcedureRequest $request, Procedure $procedure): JsonResponse
    {
        $procedure->update($request->validated());
        $procedure->load('category');

        return response()->json($procedure);
    }

    public function destroy(Procedure $procedure): JsonResponse
    {
        try {
            $procedure->delete();
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'procedure' => ['لا يمكن حذف هذه العملية لارتباطها بعمليات مسجّلة. يمكنك تعطيلها بدلاً من ذلك.'],
            ]);
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
