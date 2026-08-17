<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdmissionDepositRequest;
use App\Models\Admission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AdmissionDepositController extends Controller
{
    public function index(Admission $admission): JsonResponse
    {
        return response()->json($admission->deposits()->with('paidBy')->latest('paid_at')->get());
    }

    public function store(StoreAdmissionDepositRequest $request, Admission $admission): JsonResponse
    {
        $admission->assertMutable($request->user());

        $deposit = $admission->deposits()->create([
            ...$request->validated(),
            'paid_by' => $request->user()->id,
        ]);

        return response()->json($deposit, Response::HTTP_CREATED);
    }
}
