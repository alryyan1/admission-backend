<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNursingAssignmentRequest;
use App\Models\NursingAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NursingAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = NursingAssignment::with('nurse', 'bed.room.ward.floor');

        if ($request->filled('bed_id')) {
            $query->where('bed_id', $request->integer('bed_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('shift_start', $request->date('date'));
        }

        return response()->json($query->orderBy('shift_start')->get());
    }

    public function store(StoreNursingAssignmentRequest $request): JsonResponse
    {
        $assignment = NursingAssignment::create($request->validated());

        return response()->json($assignment->load('nurse', 'bed.room.ward.floor'), Response::HTTP_CREATED);
    }

    public function destroy(NursingAssignment $nursingAssignment): Response
    {
        $nursingAssignment->delete();

        return response()->noContent();
    }
}
