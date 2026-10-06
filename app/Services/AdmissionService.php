<?php

namespace App\Services;

use App\Jobs\SendAdmissionWhatsAppNotice;
use App\Models\Admission;
use App\Models\Bed;
use App\Models\ChartOpeningServiceSetting;
use App\Models\Patient;
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
                'referring_hospital_name' => ($data['entry_type'] ?? null) === Admission::ENTRY_TYPE_HOSPITAL_TRANSFER
                    ? ($data['referring_hospital_name'] ?? null)
                    : null,
            ]);

            $this->addChartOpeningService($admission);

            return $admission;
        });

        SendAdmissionWhatsAppNotice::dispatch($admission);

        return $admission;
    }

    /**
     * Creates an active admission for a patient without a bed or doctor. A bed is assigned later via assignBed().
     */
    public function registerPatient(Patient $patient, User $user): Admission
    {
        return DB::transaction(function () use ($patient, $user) {
            $admission = Admission::create([
                'patient_id' => $patient->id,
                'admitted_by' => $user->id,
            ]);

            $this->addChartOpeningService($admission);

            return $admission;
        });
    }

    /**
     * Assigns a bed to an admission that was registered without one.
     */
    public function assignBed(Admission $admission, int $bedId): Admission
    {
        $admission = DB::transaction(function () use ($admission, $bedId) {
            $lockedAdmission = Admission::query()->whereKey($admission->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedAdmission->status !== 'admitted' || $lockedAdmission->bed_id !== null) {
                throw ValidationException::withMessages([
                    'bed_id' => ['لا يمكن تعيين سرير لهذا التنويم.'],
                ]);
            }

            $bed = Bed::whereKey($bedId)->lockForUpdate()->firstOrFail();

            if ($bed->status !== 'available') {
                throw ValidationException::withMessages([
                    'bed_id' => ['السرير غير متاح للتنويم حالياً.'],
                ]);
            }

            $lockedAdmission->update(['bed_id' => $bed->id]);
            $bed->update(['status' => 'occupied']);

            return $lockedAdmission;
        });

        SendAdmissionWhatsAppNotice::dispatch($admission);

        return $admission;
    }

    /**
     * Clears the bed from an active admission and makes that bed available again.
     */
    public function releaseBed(Admission $admission): Admission
    {
        return DB::transaction(function () use ($admission) {
            $lockedAdmission = Admission::query()->whereKey($admission->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedAdmission->status !== 'admitted' || $lockedAdmission->bed_id === null) {
                throw ValidationException::withMessages([
                    'bed_id' => ['لا يمكن إلغاء السرير لهذا التنويم.'],
                ]);
            }

            $bed = Bed::whereKey($lockedAdmission->bed_id)->lockForUpdate()->firstOrFail();

            $lockedAdmission->update(['bed_id' => null]);
            $bed->update(['status' => 'available']);

            return $lockedAdmission;
        });
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
