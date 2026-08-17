<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBedRequest;
use App\Http\Requests\UpdateBedRequest;
use App\Models\Bed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class BedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Bed::with('room.ward.floor', 'currentAdmission.patient');

        if ($request->filled('room_id')) {
            $query->where('room_id', $request->integer('room_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->orderByRaw('CAST(bed_number AS UNSIGNED) ASC, bed_number ASC')->get());
    }

    public function store(StoreBedRequest $request): JsonResponse
    {
        $bed = Bed::create($request->validated());

        return response()->json($bed, Response::HTTP_CREATED);
    }

    public function show(Bed $bed): JsonResponse
    {
        return response()->json($bed->load('room.ward.floor', 'currentAdmission.patient'));
    }

    public function update(UpdateBedRequest $request, Bed $bed): JsonResponse
    {
        if ($request->filled('status') && $request->input('status') !== $bed->status && $bed->currentAdmission()->exists()) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تغيير حالة سرير مرتبط بتنويم نشط.'],
            ]);
        }

        $bed->update($request->validated());

        return response()->json($bed);
    }

    public function destroy(Bed $bed): Response
    {
        if ($bed->admissions()->exists()) {
            throw ValidationException::withMessages([
                'bed_id' => ['لا يمكن حذف سرير مرتبط بسجلات تنويم سابقة.'],
            ]);
        }

        $bed->delete();

        return response()->noContent();
    }
}
