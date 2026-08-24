<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\Service;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Service::with('category');

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

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = Service::create($request->validated());
        $service->load('category');

        return response()->json($service, Response::HTTP_CREATED);
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $service->update($request->validated());
        $service->load('category');

        return response()->json($service);
    }

    public function destroy(Service $service): JsonResponse
    {
        try {
            $service->delete();
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'service' => ['لا يمكن حذف هذه الخدمة لارتباطها بسجلات مرتبطة. يمكنك تعطيلها بدلاً من ذلك.'],
            ]);
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
