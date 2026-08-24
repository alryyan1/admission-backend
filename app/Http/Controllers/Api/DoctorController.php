<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDoctorRequest;
use App\Http\Requests\UpdateDoctorRequest;
use App\Models\Doctor;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class DoctorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Doctor::with('role');

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->integer('role_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('specialist', 'like', "%{$search}%");
            });
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(StoreDoctorRequest $request): JsonResponse
    {
        $doctor = Doctor::create($request->validated());
        $doctor->load('role');

        return response()->json($doctor, Response::HTTP_CREATED);
    }

    public function update(UpdateDoctorRequest $request, Doctor $doctor): JsonResponse
    {
        $doctor->update($request->validated());
        $doctor->load('role');

        return response()->json($doctor);
    }

    public function destroy(Doctor $doctor): JsonResponse
    {
        try {
            $doctor->delete();
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'doctor' => ['لا يمكن حذف هذا الطبيب لارتباطه بسجلات مرتبطة.'],
            ]);
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
