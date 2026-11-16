<?php

namespace App\Services;

use App\Models\Admission;
use Illuminate\Support\Collection;

class InvoiceService
{
    /**
     * Calculate the current, unsaved charges for an admission from live data
     * (requested services, priced operations and deposits). Room/night stay
     * charges are not billed.
     *
     * @return array<string, mixed>
     */
    public function previewCharges(Admission $admission): array
    {
        $admission->loadMissing('requestedServices', 'deposits.paymentMethod', 'operations.procedure');

        $lineItems = $this->lineItems($admission);
        $servicesTotal = round((float) $lineItems->where('is_operation', false)->sum('total_price'), 2);
        $operationsTotal = round((float) $lineItems->where('is_operation', true)->sum('total_price'), 2);
        $depositsTotal = round((float) $admission->deposits->sum('amount'), 2);

        $total = round($servicesTotal + $operationsTotal, 2);
        $balanceDue = round($total - $depositsTotal, 2);

        return [
            'requested_services' => $lineItems->values(),
            'services_total' => $servicesTotal,
            'operations_total' => $operationsTotal,
            'total' => $total,
            'deposits' => $admission->deposits,
            'deposits_total' => $depositsTotal,
            'balance_due' => $balanceDue,
        ];
    }

    /**
     * Requested services plus each operation that has a price, normalised to a
     * single billable-line shape.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function lineItems(Admission $admission): Collection
    {
        $services = $admission->requestedServices->map(fn ($service) => [
            'id' => $service->id,
            'name' => $service->name,
            'quantity' => $service->quantity,
            'unit_price' => (float) $service->unit_price,
            'total_price' => (float) $service->total_price,
            'is_auto_added' => (bool) $service->is_auto_added,
            'is_operation' => false,
            'created_at' => $service->created_at,
        ]);

        $operations = $admission->operations
            ->filter(fn ($operation) => $operation->price !== null)
            ->map(fn ($operation) => [
                'id' => 'operation-'.$operation->id,
                'name' => 'عملية: '.($operation->procedure?->name_ar ?? ('#'.$operation->operation_number)),
                'quantity' => 1,
                'unit_price' => (float) $operation->price,
                'total_price' => (float) $operation->price,
                'is_auto_added' => false,
                'is_operation' => true,
                'created_at' => $operation->scheduled_at ?? $operation->created_at,
            ]);

        return $services->concat($operations)->values();
    }
}
