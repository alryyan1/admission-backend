<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTeamMemberEntitlementRequest;
use App\Models\OperationTeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AccountantController extends Controller
{
    public function updateEntitlement(UpdateTeamMemberEntitlementRequest $request, OperationTeamMember $teamMember): JsonResponse
    {
        $validated = $request->validated();

        $this->guardEntitlementWithinOperationPrice($teamMember, $validated);
        $this->guardNameRequiredWithoutDoctor($teamMember, $validated);

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

    /**
     * A team member must be identified either by a linked doctor or a
     * free-text name; clearing both at once leaves it unidentifiable.
     *
     * @param  array<string, mixed>  $validated
     */
    private function guardNameRequiredWithoutDoctor(OperationTeamMember $teamMember, array $validated): void
    {
        if (! array_key_exists('name', $validated) && ! array_key_exists('doctor_id', $validated)) {
            return;
        }

        $effectiveDoctorId = array_key_exists('doctor_id', $validated) ? $validated['doctor_id'] : $teamMember->doctor_id;
        $effectiveName = array_key_exists('name', $validated) ? $validated['name'] : $teamMember->name;

        if ($effectiveDoctorId === null && $effectiveName === null) {
            throw ValidationException::withMessages([
                'name' => ['اسم العضو مطلوب عندما لا يوجد طبيب مرتبط.'],
            ]);
        }
    }
}
