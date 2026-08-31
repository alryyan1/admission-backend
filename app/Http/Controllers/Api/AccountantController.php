<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTeamMemberEntitlementRequest;
use App\Models\Operation;
use App\Models\OperationTeamMember;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountantController extends Controller
{
    /**
     * Patients that have at least one operation. With no search term the
     * 10 most recently operated patients are returned so the accountant's
     * autocomplete is pre-populated; a search term widens the pool.
     */
    public function operationPatients(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();

        $latestOperationAt = Operation::query()
            ->selectRaw('MAX(operations.scheduled_at)')
            ->join('admissions', 'admissions.id', '=', 'operations.admission_id')
            ->whereColumn('admissions.patient_id', 'patients.id');

        $patients = Patient::query()
            ->whereHas('admissions.operations')
            ->when($search !== '', fn ($q) => $q->where('patients.name', 'like', "%{$search}%"))
            ->select(['patients.id', 'patients.name', 'patients.phone'])
            ->selectSub($latestOperationAt, 'latest_operation_at')
            ->orderByDesc('latest_operation_at')
            ->limit($search !== '' ? 25 : 10)
            ->get();

        return response()->json($patients);
    }

    public function teamMembers(Request $request): JsonResponse
    {
        $query = OperationTeamMember::query()
            ->with([
                'doctor',
                'role',
                'paymentMethod',
                'operation.procedure',
                'operation.admission.patient',
            ]);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('doctor', fn ($d) => $d->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('operation.admission.patient', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('patient_id')) {
            $patientId = $request->integer('patient_id');
            $query->whereHas('operation.admission', fn ($a) => $a->where('patient_id', $patientId));
        }

        if ($request->filled('date_from')) {
            $dateFrom = $request->string('date_from')->toString();
            $query->whereHas('operation', fn ($o) => $o->whereDate('scheduled_at', '>=', $dateFrom));
        }

        if ($request->filled('date_to')) {
            $dateTo = $request->string('date_to')->toString();
            $query->whereHas('operation', fn ($o) => $o->whereDate('scheduled_at', '<=', $dateTo));
        }

        if ($request->filled('unpaid_only') && $request->boolean('unpaid_only')) {
            $query->whereNull('entitlement_amount');
        }

        $teamMembers = $query->latest('id')->get();

        return response()->json($teamMembers);
    }

    public function updateEntitlement(UpdateTeamMemberEntitlementRequest $request, OperationTeamMember $teamMember): JsonResponse
    {
        $validated = $request->validated();

        $this->guardEntitlementWithinOperationPrice($teamMember, $validated);

        $teamMember->update($validated);
        $teamMember->load(['doctor', 'role', 'paymentMethod', 'operation.procedure', 'operation.admission.patient']);

        return response()->json($teamMember);
    }

    /**
     * The team's combined entitlements for an operation may not exceed the
     * operation's price.
     *
     * @param  array<string, mixed>  $validated
     */
    private function guardEntitlementWithinOperationPrice(OperationTeamMember $teamMember, array $validated): void
    {
        if (! array_key_exists('entitlement_amount', $validated) || $validated['entitlement_amount'] === null) {
            return;
        }

        $teamMember->loadMissing('operation');
        $price = $teamMember->operation?->price;

        if ($price === null) {
            return;
        }

        $price = (float) $price;

        $othersTotal = (float) OperationTeamMember::query()
            ->where('operation_id', $teamMember->operation_id)
            ->whereKeyNot($teamMember->getKey())
            ->sum('entitlement_amount');

        if ($othersTotal + (float) $validated['entitlement_amount'] > $price + 0.001) {
            $remaining = max(0, $price - $othersTotal);

            throw ValidationException::withMessages([
                'entitlement_amount' => ['قيمة الاستحقاق لا يمكن أن تتجاوز المتبقي من سعر العملية ('.number_format($remaining, 2).').'],
            ]);
        }
    }
}
