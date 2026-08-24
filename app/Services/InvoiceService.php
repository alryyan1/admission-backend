<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    /**
     * Calculate the current, unsaved charges for an admission from live data
     * (requested services and deposits). Room/night stay charges are not billed.
     *
     * @return array<string, mixed>
     */
    public function previewCharges(Admission $admission): array
    {
        $admission->loadMissing('requestedServices', 'deposits.paymentMethod');

        $servicesTotal = round($admission->requestedServices->sum('total_price'), 2);
        $depositsTotal = round((float) $admission->deposits->sum('amount'), 2);

        $total = $servicesTotal;
        $balanceDue = round($total - $depositsTotal, 2);

        return [
            'requested_services' => $admission->requestedServices,
            'services_total' => $servicesTotal,
            'total' => $total,
            'deposits' => $admission->deposits,
            'deposits_total' => $depositsTotal,
            'balance_due' => $balanceDue,
        ];
    }

    public function generate(Admission $admission, User $user): Invoice
    {
        $charges = $this->previewCharges($admission);

        return DB::transaction(function () use ($admission, $user, $charges) {
            $invoice = Invoice::create([
                'admission_id' => $admission->id,
                'created_by' => $user->id,
                'subtotal' => $charges['total'],
                'discount' => 0,
                'total' => $charges['total'],
                'status' => 'issued',
                'issued_at' => now(),
            ]);

            foreach ($charges['requested_services'] as $service) {
                $invoice->items()->create([
                    'description' => $service->name,
                    'quantity' => $service->quantity,
                    'unit_price' => $service->unit_price,
                    'total' => $service->total_price,
                ]);
            }

            return $invoice;
        });
    }

    public function markPaid(Invoice $invoice): Invoice
    {
        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تحصيل فاتورة ملغاة.'],
            ]);
        }

        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return $invoice;
    }
}
