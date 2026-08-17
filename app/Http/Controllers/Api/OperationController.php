<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelOperationRequest;
use App\Http\Requests\CompleteOperationRequest;
use App\Http\Requests\StoreOperationRequest;
use App\Http\Requests\UpdateOperationRequest;
use App\Models\Admission;
use App\Models\Operation;
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
        return ['admission.patient', 'admission.bed.room.ward', 'surgeon', 'operationRoom.ward'];
    }

    public function index(Admission $admission): JsonResponse
    {
        return response()->json(
            $admission->operations()->with(['surgeon', 'operationRoom.ward'])->latest('scheduled_at')->get()
        );
    }

    public function all(Request $request): JsonResponse
    {
        $query = Operation::with($this->listRelations());

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('surgeon_id')) {
            $query->where('surgeon_id', $request->integer('surgeon_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('scheduled_at', $request->date('date'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('procedure_name', 'like', "%{$search}%")
                    ->orWhereHas('admission.patient', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        return response()->json($query->latest('scheduled_at')->paginate($request->integer('per_page', 15)));
    }

    public function show(Operation $operation): JsonResponse
    {
        return response()->json($operation->load($this->listRelations()));
    }

    public function store(StoreOperationRequest $request, Admission $admission): JsonResponse
    {
        $admission->assertMutable($request->user());

        $operation = $this->operationService->schedule($admission, $request->validated(), $request->user());

        return response()->json($operation->load(['surgeon', 'operationRoom.ward']), Response::HTTP_CREATED);
    }

    public function update(UpdateOperationRequest $request, Operation $operation): JsonResponse
    {
        $operation = $this->operationService->update($operation, $request->validated());

        return response()->json($operation->load(['surgeon', 'operationRoom.ward']));
    }

    public function start(Operation $operation): JsonResponse
    {
        $operation = $this->operationService->start($operation);

        return response()->json($operation->load(['surgeon', 'operationRoom.ward']));
    }

    public function complete(CompleteOperationRequest $request, Operation $operation): JsonResponse
    {
        $operation = $this->operationService->complete($operation, $request->validated());

        return response()->json($operation->load(['surgeon', 'operationRoom.ward']));
    }

    public function cancel(CancelOperationRequest $request, Operation $operation): JsonResponse
    {
        $operation = $this->operationService->cancel($operation, $request->validated());

        return response()->json($operation->load(['surgeon', 'operationRoom.ward']));
    }
}
