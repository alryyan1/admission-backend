<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorEntitlementsReportRequest;
use App\Models\OperationTeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DoctorEntitlementsReportController extends Controller
{
    public function index(DoctorEntitlementsReportRequest $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        $members = OperationTeamMember::query()
            ->whereNotNull('entitlement_amount')
            ->whereHas('operation', fn ($query) => $query->whereBetween('scheduled_at', [$from, $to]))
            ->with(['operation.admission.patient', 'operation.procedure', 'doctor', 'role', 'paymentMethod'])
            ->get()
            ->sortByDesc(fn (OperationTeamMember $member) => $member->operation->scheduled_at)
            ->values();

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'operations_count' => $members->pluck('operation_id')->unique()->count(),
                'total' => (float) $members->sum('entitlement_amount'),
                'paid' => (float) $members->whereNotNull('entitlement_paid_at')->sum('entitlement_amount'),
                'unpaid' => (float) $members->whereNull('entitlement_paid_at')->sum('entitlement_amount'),
            ],
            'doctors' => $this->groupByDoctor($members),
            'entitlements' => $members->map(fn (OperationTeamMember $member) => [
                'id' => $member->id,
                'operation_id' => $member->operation_id,
                'operation_number' => $member->operation->operation_number,
                'scheduled_at' => $member->operation->scheduled_at?->toIso8601String(),
                'patient_name' => $member->operation->admission?->patient?->name,
                'procedure_name' => $member->operation->procedure?->name_ar,
                'doctor_name' => $this->memberName($member),
                'role' => $member->role?->name,
                'amount' => (float) $member->entitlement_amount,
                'paid_at' => $member->entitlement_paid_at,
                'payment_method' => $member->paymentMethod?->name,
            ])->values(),
        ]);
    }

    /**
     * @param  Collection<int, OperationTeamMember>  $members
     * @return Collection<int, array{name: string, role: string|null, operations_count: int, total: float, paid: float, unpaid: float}>
     */
    private function groupByDoctor(Collection $members): Collection
    {
        return $members
            ->groupBy(fn (OperationTeamMember $member) => $member->doctor_id !== null
                ? 'doctor_'.$member->doctor_id
                : 'name_'.$this->memberName($member))
            ->map(fn (Collection $rows) => [
                'name' => $this->memberName($rows->first()),
                'role' => $rows->first()->role?->name,
                'operations_count' => $rows->pluck('operation_id')->unique()->count(),
                'total' => (float) $rows->sum('entitlement_amount'),
                'paid' => (float) $rows->whereNotNull('entitlement_paid_at')->sum('entitlement_amount'),
                'unpaid' => (float) $rows->whereNull('entitlement_paid_at')->sum('entitlement_amount'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    private function memberName(OperationTeamMember $member): string
    {
        return $member->doctor?->name ?? $member->name ?? '—';
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(DoctorEntitlementsReportRequest $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())->endOfDay()
            : now()->endOfMonth()->endOfDay();

        return [$from, $to];
    }
}
