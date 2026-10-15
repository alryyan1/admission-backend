<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayOperationRequest;
use App\Http\Requests\StoreOperationRequest;
use App\Http\Requests\StoreOperationSupplyRequest;
use App\Http\Requests\StoreOperationTeamMemberRequest;
use App\Http\Requests\UpdateOperationRequest;
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
            'deposits',
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
            ->with(['teamMembers.doctor', 'teamMembers.role', 'supplies', 'procedure.category', 'surgeon', 'paymentMethod', 'deposits'])
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

        $operations = $query->latest('scheduled_at')->paginate($request->integer('per_page', 15));

        return response()->json([
            ...$operations->toArray(),
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
        $admission->assertMutable($request->user());

        $operation = $this->operationService->schedule($admission, $request->validated(), $request->user());
        $this->loadOperation($operation);

        return response()->json($operation, Response::HTTP_CREATED);
    }

    public function update(UpdateOperationRequest $request, Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable($request->user());

        $operation = $this->operationService->update($operation, $request->validated());
        $this->loadOperation($operation);

        return response()->json($operation);
    }

    public function destroy(Request $request, Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable($request->user());

        $this->operationService->delete($operation);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Marks an operation paid and records the matching deposit in one transaction,
     * linking the two so cancelPayment() can find and remove the deposit again.
     */
    public function pay(PayOperationRequest $request, Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable($request->user());

        if ($operation->price === null) {
            throw ValidationException::withMessages([
                'price' => ['حدّد سعر العملية أولاً.'],
            ]);
        }

        if ($operation->deposits()->exists()) {
            throw ValidationException::withMessages([
                'is_paid' => ['هذه العملية مدفوعة بالفعل.'],
            ]);
        }

        $paymentMethodId = $request->integer('payment_method_id') ?: null;

        DB::transaction(function () use ($operation, $paymentMethodId, $request) {
            $operation->admission->deposits()->create([
                'operation_id' => $operation->id,
                'amount' => $operation->price,
                'payment_method_id' => $paymentMethodId,
                'comment' => 'سداد: '.($operation->procedure?->name_ar ?? 'عملية #'.$operation->id),
                'paid_by' => $request->user()->id,
            ]);

            $operation->update(['payment_method_id' => $paymentMethodId]);
        });

        return response()->json($operation->fresh(['paymentMethod', 'deposits']));
    }

    /**
     * Reverts pay(): deletes the deposit(s) it created for this operation and clears the paid flag.
     */
    public function cancelPayment(Request $request, Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable($request->user());

        DB::transaction(function () use ($operation) {
            $operation->deposits()->delete();
            $operation->update(['payment_method_id' => null]);
        });

        return response()->json($operation->fresh(['paymentMethod', 'deposits']));
    }

    public function addTeamMember(StoreOperationTeamMemberRequest $request, Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable($request->user());
        $operation->assertEntitlementsEditable($request->user());

        $member = $operation->teamMembers()->create([
            ...$request->validated(),
            'payment_method_id' => PaymentMethod::query()
                ->where('name', self::DEFAULT_TEAM_MEMBER_PAYMENT_METHOD_NAME)
                ->value('id'),
        ]);
        $member->load(['doctor', 'role']);

        return response()->json($member, Response::HTTP_CREATED);
    }

    public function removeTeamMember(Request $request, Operation $operation, OperationTeamMember $teamMember): JsonResponse
    {
        abort_unless($teamMember->operation_id === $operation->id, Response::HTTP_NOT_FOUND);

        $operation->admission->assertMutable($request->user());
        $operation->assertEntitlementsEditable($request->user());

        $teamMember->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Locks the team's entitlement distribution: roles, doctors, entitlement
     * amounts and payment methods can no longer be edited until unapproved.
     */
    public function approveEntitlements(Request $request, Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable($request->user());

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
     * Reopens the entitlement distribution for editing. Admin-only, mirroring
     * the admin override in Operation::assertEntitlementsEditable().
     */
    public function unapproveEntitlements(Request $request, Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable($request->user());

        $operation->forceFill([
            'entitlements_approved_at' => null,
            'entitlements_approved_by' => null,
        ])->save();

        return response()->json($this->loadOperation($operation));
    }

    public function addSupply(StoreOperationSupplyRequest $request, Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable($request->user());

        $supply = $operation->supplies()->create($request->validated());

        return response()->json($supply, Response::HTTP_CREATED);
    }

    public function removeSupply(Request $request, Operation $operation, OperationSupply $supply): JsonResponse
    {
        abort_unless($supply->operation_id === $operation->id, Response::HTTP_NOT_FOUND);

        $operation->admission->assertMutable($request->user());

        $supply->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
