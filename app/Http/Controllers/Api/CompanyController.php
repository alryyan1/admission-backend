<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CompanyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Company::orderBy('name')->get());
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = Company::create($request->validated());

        return response()->json($company, Response::HTTP_CREATED);
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $company->update($request->validated());

        return response()->json($company);
    }

    public function destroy(Company $company): JsonResponse
    {
        $company->patients()->update(['company_id' => null]);
        $company->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
