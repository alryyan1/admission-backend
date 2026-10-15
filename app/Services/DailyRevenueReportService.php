<?php

namespace App\Services;

use App\Models\AdmissionDeposit;
use App\Models\PaymentMethod;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DailyRevenueReportService
{
    private const UNASSIGNED_KEY = 'unassigned';

    /**
     * @return array{month: string, range: array{from: string, to: string}, payment_methods: Collection<int, array{key: string, name: string}>, days: Collection<int, array{date: string, amounts: array<string, float>, total: float}>, totals: array{amounts: array<string, float>, total: float}}
     */
    public function build(?string $month): array
    {
        $monthDate = $month !== null
            ? Carbon::createFromFormat('Y-m', $month)
            : now();

        $from = $monthDate->copy()->startOfMonth()->startOfDay();
        $to = $monthDate->copy()->endOfMonth()->endOfDay();

        $deposits = AdmissionDeposit::query()
            ->whereBetween('paid_at', [$from, $to])
            ->get(['id', 'amount', 'paid_at', 'payment_method_id']);

        $paymentMethods = $this->paymentMethodColumns($deposits);
        $depositsByDate = $deposits->groupBy(fn (AdmissionDeposit $deposit) => $deposit->paid_at->toDateString());

        $days = collect(CarbonPeriod::create($from, $to))->map(function (Carbon $day) use ($depositsByDate, $paymentMethods) {
            $dayDeposits = $depositsByDate->get($day->toDateString(), collect());

            return [
                'date' => $day->toDateString(),
                'amounts' => $this->amountsByColumn($dayDeposits, $paymentMethods),
                'total' => (float) $dayDeposits->sum('amount'),
            ];
        })->values();

        return [
            'month' => $from->format('Y-m'),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'payment_methods' => $paymentMethods->values(),
            'days' => $days,
            'totals' => [
                'amounts' => $this->amountsByColumn($deposits, $paymentMethods),
                'total' => (float) $deposits->sum('amount'),
            ],
        ];
    }

    /**
     * Every payment method gets its own column, even with zero activity in the period,
     * so the report's shape stays stable from month to month.
     *
     * @param  Collection<int, AdmissionDeposit>  $deposits
     * @return Collection<int, array{key: string, name: string}>
     */
    private function paymentMethodColumns(Collection $deposits): Collection
    {
        $columns = PaymentMethod::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (PaymentMethod $paymentMethod) => [
                'key' => $this->paymentMethodKey($paymentMethod->id),
                'name' => $paymentMethod->name,
            ]);

        if ($deposits->contains(fn (AdmissionDeposit $deposit) => $deposit->payment_method_id === null)) {
            $columns->push(['key' => self::UNASSIGNED_KEY, 'name' => 'غير محدد']);
        }

        return $columns;
    }

    /**
     * @param  Collection<int, AdmissionDeposit>  $deposits
     * @param  Collection<int, array{key: string, name: string}>  $paymentMethods
     * @return array<string, float>
     */
    private function amountsByColumn(Collection $deposits, Collection $paymentMethods): array
    {
        $amounts = [];

        foreach ($paymentMethods as $column) {
            $amounts[$column['key']] = (float) $deposits
                ->filter(fn (AdmissionDeposit $deposit) => $this->depositColumnKey($deposit) === $column['key'])
                ->sum('amount');
        }

        return $amounts;
    }

    private function depositColumnKey(AdmissionDeposit $deposit): string
    {
        return $deposit->payment_method_id === null
            ? self::UNASSIGNED_KEY
            : $this->paymentMethodKey($deposit->payment_method_id);
    }

    private function paymentMethodKey(int $paymentMethodId): string
    {
        return 'method_'.$paymentMethodId;
    }
}
