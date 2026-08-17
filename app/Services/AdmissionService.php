<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Bed;
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
        return DB::transaction(function () use ($data, $user) {
            $bed = Bed::with('room')->whereKey($data['bed_id'])->lockForUpdate()->firstOrFail();

            if ($bed->status !== 'available') {
                throw ValidationException::withMessages([
                    'bed_id' => ['السرير غير متاح للتنويم حالياً.'],
                ]);
            }

            return Admission::create([
                ...$data,
                'admitted_by' => $user->id,
                'admission_type' => $bed->room->is_short_stay ? 'short_stay' : 'inpatient',
            ]);
        });
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
