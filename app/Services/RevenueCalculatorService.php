<?php

namespace App\Services;

use App\Models\AdmissionDeposit;
use App\Models\Expense;
use App\Models\OperationTeamMember;
use App\Models\PaymentMethod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RevenueCalculatorService
{
    /**
     * @return array{
     *     date: string,
     *     payment_methods: array<int, string>,
     *     rows: array<int, array{label: string, amounts: array<string, float>, total: float}>,
     * }
     */
    public function calculate(Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $paymentMethods = PaymentMethod::query()->where('is_active', true)->orderBy('id')->pluck('name')->all();

        $revenueByMethod = AdmissionDeposit::query()
            ->join('payment_methods', 'payment_methods.id', '=', 'admission_deposits.payment_method_id')
            ->whereBetween('admission_deposits.paid_at', [$start, $end])
            ->selectRaw('payment_methods.name as method, SUM(admission_deposits.amount) as total')
            ->groupBy('payment_methods.name')
            ->pluck('total', 'method');

        $expensesByMethod = Expense::query()
            ->join('payment_methods', 'payment_methods.id', '=', 'expenses.payment_method_id')
            ->whereBetween('expenses.expense_date', [$start, $end])
            ->selectRaw('payment_methods.name as method, SUM(expenses.amount) as total')
            ->groupBy('payment_methods.name')
            ->pluck('total', 'method');

        $entitlementsByMethod = OperationTeamMember::query()
            ->join('payment_methods', 'payment_methods.id', '=', 'operation_team_members.payment_method_id')
            ->whereBetween('operation_team_members.entitlement_paid_at', [$start, $end])
            ->selectRaw('payment_methods.name as method, SUM(operation_team_members.entitlement_amount) as total')
            ->groupBy('payment_methods.name')
            ->pluck('total', 'method');

        $revenueRow = $this->buildRow('الإيرادات', $paymentMethods, $revenueByMethod);
        $expensesRow = $this->buildRow('المصروفات', $paymentMethods, $expensesByMethod);
        $entitlementsRow = $this->buildRow('استحقاقات العميلة', $paymentMethods, $entitlementsByMethod);
        $netRow = $this->buildNetRow($paymentMethods, $revenueRow, $expensesRow, $entitlementsRow);

        return [
            'date' => $date->toDateString(),
            'payment_methods' => $paymentMethods,
            'rows' => [$revenueRow, $expensesRow, $entitlementsRow, $netRow],
        ];
    }

    /**
     * @param  array<int, string>  $paymentMethods
     * @param  Collection<string, mixed>  $byMethod
     * @return array{label: string, amounts: array<string, float>, total: float}
     */
    private function buildRow(string $label, array $paymentMethods, Collection $byMethod): array
    {
        $amounts = [];
        foreach ($paymentMethods as $method) {
            $amounts[$method] = round((float) ($byMethod[$method] ?? 0), 2);
        }

        return [
            'label' => $label,
            'amounts' => $amounts,
            'total' => round(array_sum($amounts), 2),
        ];
    }

    /**
     * @param  array<int, string>  $paymentMethods
     * @param  array{label: string, amounts: array<string, float>, total: float}  $revenueRow
     * @param  array{label: string, amounts: array<string, float>, total: float}  $expensesRow
     * @param  array{label: string, amounts: array<string, float>, total: float}  $entitlementsRow
     * @return array{label: string, amounts: array<string, float>, total: float}
     */
    private function buildNetRow(array $paymentMethods, array $revenueRow, array $expensesRow, array $entitlementsRow): array
    {
        $amounts = [];
        foreach ($paymentMethods as $method) {
            $amounts[$method] = round(
                $revenueRow['amounts'][$method] - $expensesRow['amounts'][$method] - $entitlementsRow['amounts'][$method],
                2
            );
        }

        return [
            'label' => 'الصافي',
            'amounts' => $amounts,
            'total' => round($revenueRow['total'] - $expensesRow['total'] - $entitlementsRow['total'], 2),
        ];
    }
}
