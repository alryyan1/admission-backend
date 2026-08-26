<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdmissionDepositRequest;
use App\Models\Admission;
use App\Models\AdmissionDeposit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdmissionDepositController extends Controller
{
    public function index(Admission $admission): JsonResponse
    {
        return response()->json($admission->deposits()->with(['paidBy', 'paymentMethod'])->latest('paid_at')->get());
    }

    public function store(StoreAdmissionDepositRequest $request, Admission $admission): JsonResponse
    {
        $admission->assertMutable($request->user());

        $deposit = $admission->deposits()->create([
            ...$request->validated(),
            'paid_by' => $request->user()->id,
        ]);

        return response()->json($deposit->load('paymentMethod'), Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Admission $admission, AdmissionDeposit $deposit): JsonResponse
    {
        abort_unless($deposit->admission_id === $admission->id, Response::HTTP_NOT_FOUND);

        $admission->assertMutable($request->user());

        $deposit->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
