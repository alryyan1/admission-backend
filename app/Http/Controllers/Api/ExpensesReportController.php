<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ExpensesReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        $expenses = $this->scopedQuery($request, $from, $to)
            ->with(['paymentMethod', 'recordedBy'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'count' => $expenses->count(),
                'total_amount' => (float) $expenses->sum('amount'),
            ],
            'by_category' => $this->groupByCategory($expenses),
            'by_method' => $this->groupByMethod($expenses),
            'expenses' => $expenses->map(fn (Expense $expense) => [
                'id' => $expense->id,
                'expense_date' => $expense->expense_date->toDateString(),
                'category' => $expense->category,
                'description' => $expense->description,
                'amount' => (float) $expense->amount,
                'payment_method' => $expense->paymentMethod?->name,
                'recorded_by' => $expense->recordedBy?->name,
            ])->values(),
        ]);
    }

    /**
     * @return Collection<int, array{category: string, count: int, total: float}>
     */
    private function groupByCategory(Collection $expenses): Collection
    {
        return $expenses
            ->groupBy(fn (Expense $expense) => $expense->category)
            ->map(fn (Collection $rows, string $category) => [
                'category' => $category,
                'count' => $rows->count(),
                'total' => (float) $rows->sum('amount'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * @return Collection<int, array{method: string, count: int, total: float}>
     */
    private function groupByMethod(Collection $expenses): Collection
    {
        return $expenses
            ->groupBy(fn (Expense $expense) => $expense->paymentMethod?->name ?? 'غير محدد')
            ->map(fn (Collection $rows, string $method) => [
                'method' => $method,
                'count' => $rows->count(),
                'total' => (float) $rows->sum('amount'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    private function scopedQuery(Request $request, Carbon $from, Carbon $to)
    {
        $query = Expense::query()->whereBetween('expense_date', [$from, $to]);

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('search')) {
            $query->where('description', 'like', '%'.$request->string('search').'%');
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
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfMonth();

        return [$from, $to];
    }
}
