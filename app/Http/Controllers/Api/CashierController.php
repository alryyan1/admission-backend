<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionDeposit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashierController extends Controller
{
    public function admissions(Request $request): JsonResponse
    {
        $query = Admission::query()
            ->with(['patient', 'bed.room.ward.floor', 'requestedServices', 'deposits', 'operations.procedure'])
            ->where('status', 'admitted');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->whereHas('patient', fn ($patientQuery) => $patientQuery->where('name', 'like', "%{$search}%"));
        }

        $admissions = $query->get()
            ->map(function (Admission $admission) {
                $servicesTotal = round($admission->requestedServices->sum('total_price'), 2);
                $operationsTotal = round((float) $admission->operations->whereNotNull('price')->sum('price'), 2);
                $depositsTotal = round((float) $admission->deposits->sum('amount'), 2);

                return [
                    'id' => $admission->id,
                    'admission_number' => $admission->admission_number,
                    'admission_date' => $admission->admission_date,
                    'patient' => $admission->patient,
                    'bed' => $admission->bed,
                    'services_total' => $servicesTotal,
                    'operations_total' => $operationsTotal,
                    'deposits_total' => $depositsTotal,
                    'balance_due' => round($servicesTotal + $operationsTotal - $depositsTotal, 2),
                    'operations' => $admission->operations,
                ];
            })
            ->sortByDesc('balance_due')
            ->values();

        $dateFrom = $request->filled('date_from') ? $request->string('date_from')->toString() : now()->toDateString();
        $dateTo = $request->filled('date_to') ? $request->string('date_to')->toString() : now()->toDateString();

        $depositsInRange = AdmissionDeposit::query()
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo);

        $depositsByPaymentMethod = (clone $depositsInRange)
            ->with('paymentMethod')
            ->get()
            ->groupBy(fn (AdmissionDeposit $deposit) => $deposit->payment_method_id ?? 0)
            ->map(function ($deposits) {
                $paymentMethod = $deposits->first()->paymentMethod;

                return [
                    'payment_method_id' => $paymentMethod?->id,
                    'payment_method_name' => $paymentMethod?->name ?? 'غير محدد',
                    'total' => round((float) $deposits->sum('amount'), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $summary = [
            'admissions_count' => $admissions->count(),
            'admissions_with_balance' => $admissions->where('balance_due', '>', 0)->count(),
            'total_outstanding' => round((float) $admissions->sum('balance_due'), 2),
            'deposits_today' => round((float) (clone $depositsInRange)->sum('amount'), 2),
            'deposits_by_payment_method' => $depositsByPaymentMethod,
        ];

        return response()->json([
            'summary' => $summary,
            'admissions' => $admissions,
        ]);
    }
}
