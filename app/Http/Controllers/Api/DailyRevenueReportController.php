<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyRevenueReportRequest;
use App\Models\AdmissionDeposit;
use App\Models\PaymentMethod;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DailyRevenueReportController extends Controller
{
    private const UNASSIGNED_KEY = 'unassigned';

    public function index(DailyRevenueReportRequest $request): JsonResponse
    {
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month')->toString())
            : now();

        $from = $month->copy()->startOfMonth()->startOfDay();
        $to = $month->copy()->endOfMonth()->endOfDay();

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

        return response()->json([
            'month' => $from->format('Y-m'),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'payment_methods' => $paymentMethods->values(),
            'days' => $days,
            'totals' => [
                'amounts' => $this->amountsByColumn($deposits, $paymentMethods),
                'total' => (float) $deposits->sum('amount'),
            ],
        ]);
    }

    /**
     * @param  Collection<int, AdmissionDeposit>  $deposits
     * @return Collection<int, array{key: string, name: string}>
     */
    private function paymentMethodColumns(Collection $deposits): Collection
    {
        $columns = PaymentMethod::query()
            ->whereIn('id', $deposits->pluck('payment_method_id')->filter()->unique())
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
