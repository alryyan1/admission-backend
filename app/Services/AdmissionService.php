<?php

namespace App\Services;

use App\Jobs\SendAdmissionWhatsAppNotice;
use App\Models\Admission;
use App\Models\Bed;
use App\Models\ChartOpeningServiceSetting;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmissionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function admit(array $data, User $user): Admission
    {
        $admission = DB::transaction(function () use ($data, $user) {
            $bed = Bed::with('room')->whereKey($data['bed_id'])->lockForUpdate()->firstOrFail();

            if ($bed->status !== 'available') {
                throw ValidationException::withMessages([
                    'bed_id' => ['السرير غير متاح للتنويم حالياً.'],
                ]);
            }

            $admission = Admission::create([
                ...$data,
                'admitted_by' => $user->id,
                'admission_type' => 'inpatient',
            ]);

            $this->addChartOpeningService($admission);

            return $admission;
        });

        SendAdmissionWhatsAppNotice::dispatch($admission);

        return $admission;
    }

    private function addChartOpeningService(Admission $admission): void
    {
        $setting = ChartOpeningServiceSetting::current()->load('service');

        if (! $setting->auto_add || ! $setting->service) {
            return;
        }

        $this->createAutoRequestedService($admission, $setting->service);
    }

    private function createAutoRequestedService(Admission $admission, Service $service): void
    {
        $admission->requestedServices()->create([
            'name' => $service->name_ar,
            'quantity' => 1,
            'unit_price' => $service->price,
            'is_auto_added' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function discharge(Admission $admission, array $data, User $user): Admission
    {
        if ($admission->status !== 'admitted') {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن إخراج تنويم غير نشط.'],
            ]);
        }

        $admission->update([
            'status' => 'discharged',
            'discharge_date' => $data['discharge_date'] ?? now(),
            'discharge_summary' => $data['discharge_summary'] ?? null,
            'discharged_by' => $user->id,
        ]);

        return $admission;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function cancel(Admission $admission, array $data, User $user): Admission
    {
        if ($admission->status !== 'admitted') {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن إلغاء تنويم غير نشط.'],
            ]);
        }

        $admission->update([
            'status' => 'cancelled',
            'cancellation_reason' => $data['cancellation_reason'] ?? null,
            'cancelled_by' => $user->id,
            'cancelled_at' => now(),
        ]);

        return $admission;
    }
}
