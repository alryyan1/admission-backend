<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInsuranceCompanyRequest;
use App\Http\Requests\UpdateInsuranceCompanyRequest;
use App\Models\InsuranceCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class InsuranceCompanyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(InsuranceCompany::orderBy('name')->get());
    }

    public function store(StoreInsuranceCompanyRequest $request): JsonResponse
    {
        $insuranceCompany = InsuranceCompany::create($request->validated());

        return response()->json($insuranceCompany, Response::HTTP_CREATED);
    }

    public function update(UpdateInsuranceCompanyRequest $request, InsuranceCompany $insuranceCompany): JsonResponse
    {
        $insuranceCompany->update($request->validated());

        return response()->json($insuranceCompany);
    }

    public function destroy(InsuranceCompany $insuranceCompany): JsonResponse
    {
        $insuranceCompany->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
