<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOperationRequest;
use App\Http\Requests\StoreOperationSupplyRequest;
use App\Http\Requests\StoreOperationTeamMemberRequest;
use App\Http\Requests\UpdateOperationRequest;
use App\Models\Admission;
use App\Models\Operation;
use App\Models\OperationSupply;
use App\Models\OperationTeamMember;
use App\Services\OperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OperationController extends Controller
{
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
            ->with(['teamMembers.doctor', 'teamMembers.role', 'supplies', 'procedure.category', 'surgeon'])
            ->latest('scheduled_at')
            ->get();

        return response()->json($operations);
    }

    public function all(Request $request): JsonResponse
    {
        $query = Operation::with($this->listRelations());

        if ($request->filled('surgeon_id')) {
            $query->where('surgeon_id', $request->integer('surgeon_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('scheduled_at', $request->date('date'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('procedure', fn ($p) => $p->where('name_ar', 'like', "%{$search}%")->orWhere('name_en', 'like', "%{$search}%"))
                    ->orWhereHas('admission.patient', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        $operations = $query->latest('scheduled_at')->paginate($request->integer('per_page', 15));

        return response()->json($operations);
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

    public function addTeamMember(StoreOperationTeamMemberRequest $request, Operation $operation): JsonResponse
    {
        $operation->admission->assertMutable($request->user());

        $member = $operation->teamMembers()->create($request->validated());
        $member->load(['doctor', 'role']);

        return response()->json($member, Response::HTTP_CREATED);
    }

    public function removeTeamMember(Request $request, Operation $operation, OperationTeamMember $teamMember): JsonResponse
    {
        abort_unless($teamMember->operation_id === $operation->id, Response::HTTP_NOT_FOUND);

        $operation->admission->assertMutable($request->user());

        $teamMember->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
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
