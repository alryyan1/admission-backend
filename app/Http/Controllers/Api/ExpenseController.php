<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Expense::query()->with(['paymentMethod', 'recordedBy']);

        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->string('date_to'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('search')) {
            $query->where('description', 'like', '%'.$request->string('search').'%');
        }

        return response()->json(
            $query->orderByDesc('expense_date')->orderByDesc('id')->get()
        );
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = Expense::create([
            ...$request->validated(),
            'recorded_by_id' => $request->user()->id,
        ]);

        return response()->json($expense->load(['paymentMethod', 'recordedBy']), Response::HTTP_CREATED);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense->update($request->validated());

        return response()->json($expense->load(['paymentMethod', 'recordedBy']));
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
