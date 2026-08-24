<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Invoice;
use App\Models\ShortStayServiceSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    /**
     * Calculate the current, unsaved charges for an admission from live data
     * (room pricing, requested services and deposits).
     *
     * @return array<string, mixed>
     */
    public function previewCharges(Admission $admission): array
    {
        $admission->loadMissing('bed.room', 'requestedServices', 'deposits');

        $room = $admission->bed->room;
        $nights = null;
        $pricePerDay = null;

        if ($room->is_short_stay) {
            $shortStaySetting = ShortStayServiceSetting::current()->load(['service12h', 'service24h']);
            $durationService = match ($admission->admission_duration_hours) {
                12 => $shortStaySetting->service12h,
                24 => $shortStaySetting->service24h,
                default => null,
            };

            if ($shortStaySetting->enabled && $durationService) {
                // The auto-added requested service already bills this stay's duration,
                // so the room's own hourly price is not charged again here.
                $bedCharges = 0.0;
                $bedChargeDescription = null;
            } else {
                $bedCharges = match ($admission->admission_duration_hours) {
                    12 => $room->price_12_hours,
                    24 => $room->price_24_hours,
                    default => null,
                };

                if ($bedCharges === null) {
                    throw ValidationException::withMessages([
                        'admission_duration_hours' => ['تسعير الإقامة القصيرة لهذه الغرفة غير مكتمل بعد.'],
                    ]);
                }

                $bedCharges = round((float) $bedCharges, 2);
                $bedChargeDescription = "إقامة قصيرة — {$admission->admission_duration_hours} ساعة";
            }
        } else {
            $nights = $admission->nightsStayed();
            $pricePerDay = (float) $room->price_per_day;
            $bedCharges = round($nights * $pricePerDay, 2);
            $bedChargeDescription = "إقامة — {$nights} ليلة";
        }

        $servicesTotal = round($admission->requestedServices->sum('total_price'), 2);
        $depositsTotal = round((float) $admission->deposits->sum('amount'), 2);

        $total = round($bedCharges + $servicesTotal, 2);
        $balanceDue = round($total - $depositsTotal, 2);

        return [
            'billing_mode' => $room->is_short_stay ? 'short_stay' : 'daily',
            'nights_stayed' => $nights,
            'price_per_day' => $pricePerDay,
            'admission_duration_hours' => $admission->admission_duration_hours,
            'bed_charge_description' => $bedChargeDescription,
            'bed_charges' => $bedCharges,
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

            if ($charges['bed_charge_description'] !== null) {
                $invoice->items()->create([
                    'description' => $charges['bed_charge_description'],
                    'quantity' => 1,
                    'unit_price' => $charges['bed_charges'],
                    'total' => $charges['bed_charges'],
                ]);
            }

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
