<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdmissionDeposit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PaymentsReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        $payments = $this->scopedQuery($request, $from, $to)
            ->with(['admission.patient', 'paymentMethod', 'paidBy'])
            ->orderByDesc('paid_at')
            ->get();

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'count' => $payments->count(),
                'total_amount' => (float) $payments->sum('amount'),
            ],
            'by_method' => $this->groupByMethod($payments),
            'payments' => $payments->map(fn (AdmissionDeposit $deposit) => [
                'id' => $deposit->id,
                'admission_id' => $deposit->admission_id,
                'patient_name' => $deposit->admission?->patient?->name,
                'amount' => (float) $deposit->amount,
                'payment_method' => $deposit->paymentMethod?->name,
                'comment' => $deposit->comment,
                'paid_by' => $deposit->paidBy?->name,
                'paid_at' => $deposit->paid_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * @return Collection<int, array{method: string, count: int, total: float}>
     */
    private function groupByMethod(Collection $payments): Collection
    {
        return $payments
            ->groupBy(fn (AdmissionDeposit $deposit) => $deposit->paymentMethod?->name ?? '—')
            ->map(fn (Collection $rows, string $method) => [
                'method' => $method,
                'count' => $rows->count(),
                'total' => (float) $rows->sum('amount'),
            ])
            ->values();
    }

    private function scopedQuery(Request $request, Carbon $from, Carbon $to)
    {
        $query = AdmissionDeposit::query()->whereBetween('paid_at', [$from, $to]);

        if ($request->filled('payment_method_id')) {
            $query->where('payment_method_id', $request->integer('payment_method_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->whereHas('admission.patient', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        return $query;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : now()->subDays(29)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }
}
