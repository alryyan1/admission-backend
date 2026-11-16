<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayOperationRequest;
use App\Http\Requests\StoreOperationRequest;
use App\Http\Requests\StoreOperationSupplyRequest;
use App\Http\Requests\StoreOperationTeamMemberRequest;
use App\Http\Requests\UpdateOperationRequest;
use App\Http\Requests\UpdateTeamMemberEntitlementRequest;
use App\Models\Admission;
use App\Models\Operation;
use App\Models\OperationSupply;
use App\Models\OperationTeamMember;
use App\Models\PaymentMethod;
use App\Services\OperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationController extends Controller
{
    /** Payment method pre-selected for a newly added team member when none is specified. */
    private const DEFAULT_TEAM_MEMBER_PAYMENT_METHOD_NAME = 'بنكك';

    public function __construct(private readonly OperationService $operationService) {}

    /**
     * @return array<int, mixed>
     */
    private function listRelations(): array
    {
        return [
            'admission.patient',
            'admission.bed.room.ward',
            'teamMembers.doctor',
            'teamMembers.role',
            'teamMembers.paymentMethod',
            'supplies',
            'procedure.category',
            'surgeon',
            'paymentMethod',
            'deposits.paymentMethod',
            'deposits.paidBy',
        ];
    }

    private function loadOperation(Operation $operation): Operation
    {
        $operation->load($this->listRelations());

        return $operation;
    }

    public function index(Admission $admission): JsonResponse
    {
        $operations = $admission->operations()
            ->with([
                'teamMembers.doctor',
                'teamMembers.role',
                'supplies',
                'procedure.category',
                'surgeon',
                'paymentMethod',
                'deposits.paymentMethod',
                'deposits.paidBy',
            ])
            ->latest('scheduled_at')
            ->get();

        return response()->json($operations);
    }

    public function all(Request $request): JsonResponse
    {
        $query = $this->operationService->filteredQuery($request)->with($this->listRelations());

        $priceTotal = (float) (clone $query)->sum('price');
        $entitlementsTotal = (float) OperationTeamMember::query()
            ->whereIn('operation_id', (clone $query)->select('id'))
            ->sum('entitlement_amount');

        $operations = $query->latest('scheduled_at')->get();

        return response()->json([
            'data' => $operations,
            'price_total' => $priceTotal,
            'net_total' => $priceTotal - $entitlementsTotal,
        ]);
    }

    public function show(Operation $operation): JsonResponse
    {
        $this->loadOperation($operation);

        return response()->json($operation);
    }

    public function store(StoreOperationRequest $request, Admission $admission): JsonResponse
    {
        $admission->assertMutable();

        $operation = $this->operationService->schedule($admission, $request->validated(), $request->user());
        $this->loadOperation($operation);

        return response()->json($operation, Response::HTTP_CREATED);
    }

    public function update(UpdateOperationRequest $request, Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable();

        $operation = $this->operationService->update($operation, $request->validated());
        $this->loadOperation($operation);

        return response()->json($operation);
    }

    public function destroy(Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable();

        $this->operationService->delete($operation);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Records a deposit toward the operation's price, for the given amount — which may
     * be partial. Individual payments are later removed via AdmissionDepositController::destroy,
     * not reverted as a whole, so multiple deposits can accumulate per operation.
     */
    public function pay(PayOperationRequest $request, Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable();

        if ($operation->price === null) {
            throw ValidationException::withMessages([
                'price' => ['حدّد سعر العملية أولاً.'],
            ]);
        }

        if ($operation->remaining_amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['هذه العملية مدفوعة بالكامل.'],
            ]);
        }

        $amount = (float) $request->input('amount');
        $paymentMethodId = $request->integer('payment_method_id') ?: null;

        DB::transaction(function () use ($operation, $amount, $paymentMethodId, $request) {
            $operation->admission->deposits()->create([
                'operation_id' => $operation->id,
                'amount' => $amount,
                'payment_method_id' => $paymentMethodId,
                'comment' => 'سداد: '.($operation->procedure?->name_ar ?? 'عملية #'.$operation->id),
                'paid_by' => $request->user()->id,
            ]);

            // Convenience default for the row's payment-method select / next payment dialog;
            // each deposit records its own payment_method_id as the actual source of truth.
            $operation->update(['payment_method_id' => $paymentMethodId]);
        });

        return response()->json($operation->fresh(['paymentMethod', 'deposits.paymentMethod', 'deposits.paidBy']));
    }

    public function addTeamMember(StoreOperationTeamMemberRequest $request, Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable();
        $operation->assertEntitlementsEditable();

        $member = $operation->teamMembers()->create([
            ...$request->validated(),
            'payment_method_id' => PaymentMethod::query()
                ->where('name', self::DEFAULT_TEAM_MEMBER_PAYMENT_METHOD_NAME)
                ->value('id'),
        ]);
        $member->load(['doctor', 'role']);

        return response()->json($member, Response::HTTP_CREATED);
    }

    public function removeTeamMember(Operation $operation, OperationTeamMember $teamMember): JsonResponse
    {
        abort_unless($teamMember->operation_id === $operation->id, Response::HTTP_NOT_FOUND);

        $operation->admission->assertMutable();
        $operation->assertEntitlementsEditable();

        $teamMember->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Locks the team's entitlement distribution: roles, doctors, entitlement
     * amounts and payment methods can no longer be edited until unapproved.
     */
    public function approveEntitlements(Request $request, Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable();

        if ($operation->entitlements_approved_at !== null) {
            throw ValidationException::withMessages([
                'entitlements_approved_at' => ['تم اعتماد توزيع الاستحقاقات بالفعل.'],
            ]);
        }

        $operation->forceFill([
            'entitlements_approved_at' => now(),
            'entitlements_approved_by' => $request->user()->id,
        ])->save();

        return response()->json($this->loadOperation($operation));
    }

    /**
     * Reopens the entitlement distribution for editing. Admin-only (see the
     * role:admin middleware on this route).
     */
    public function unapproveEntitlements(Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable();

        $operation->forceFill([
            'entitlements_approved_at' => null,
            'entitlements_approved_by' => null,
        ])->save();

        return response()->json($this->loadOperation($operation));
    }

    public function addSupply(StoreOperationSupplyRequest $request, Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable();

        $supply = $operation->supplies()->create($request->validated());

        return response()->json($supply, Response::HTTP_CREATED);
    }

    public function removeSupply(Operation $operation, OperationSupply $supply): JsonResponse
    {
        abort_unless($supply->operation_id === $operation->id, Response::HTTP_NOT_FOUND);

        $operation->admission->assertMutable();

        $supply->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function updateTeamMemberEntitlement(UpdateTeamMemberEntitlementRequest $request, OperationTeamMember $teamMember): JsonResponse
    {
        $teamMember->loadMissing('operation.admission');
        $teamMember->operation->admission->assertMutable();
        $teamMember->operation->assertEntitlementsEditable();

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
