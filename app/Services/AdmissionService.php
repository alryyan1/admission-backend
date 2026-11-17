<?php

namespace App\Services;

use App\Jobs\SendAdmissionWhatsAppNotice;
use App\Models\Admission;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdmissionService
{
    public function __construct(private readonly JawdaMedicalClient $jawdaMedicalClient) {}

    /**
     * The status/patient/search/bed-room/date filters shared by the admissions listing
     * and the admissions payments PDF report.
     */
    public function filteredQuery(Request $request): Builder
    {
        $query = Admission::query();

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();

            if ($status === 'unassigned') {
                $query->where('status', 'admitted')->whereNull('bed_id');
            } else {
                $query->where('status', $status);

                if ($status === 'admitted') {
                    $query->whereNotNull('bed_id');
                }
            }
        }

        if ($request->boolean('unapproved_operations_only')) {
            $query->whereHas('operations', fn ($operationsQuery) => $operationsQuery->whereNull('entitlements_approved_at'));
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->integer('patient_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->whereHas('patient', fn ($patientQuery) => $patientQuery->where('name', 'like', "%{$search}%"));
        }

        if ($request->filled('admission_id')) {
            $query->where('id', $request->integer('admission_id'));
        }

        if ($request->filled('bed_id')) {
            $query->where('bed_id', $request->integer('bed_id'));
        } elseif ($request->filled('room_id')) {
            $roomId = $request->integer('room_id');
            $query->whereHas('bed', fn ($bedQuery) => $bedQuery->where('room_id', $roomId));
        }

        if ($request->filled('from')) {
            $query->where('admission_date', '>=', Carbon::parse($request->query('from')));
        }

        if ($request->filled('to')) {
            $query->where('admission_date', '<=', Carbon::parse($request->query('to')));
        }

        return $query;
    }

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

            return $admission;
        });

        SendAdmissionWhatsAppNotice::dispatch($admission);
        $this->linkToJawdaMedical($admission);

        return $admission;
    }

    /**
     * Creates an active admission for a patient without a bed or doctor. A bed is assigned later via assignBed().
     */
    public function registerPatient(Patient $patient, User $user): Admission
    {
        $admission = DB::transaction(function () use ($patient, $user) {
            $hasActiveAdmission = Admission::query()
                ->where('patient_id', $patient->id)
                ->where('status', 'admitted') // admitted ده التنويم معناه نشط
                ->lockForUpdate()
                ->exists();

            if ($hasActiveAdmission) {
                throw ValidationException::withMessages([
                    'patient_id' => ['هذا المريض لديه تنويم نشط بالفعل.'],
                ]);
            }

            $admission = Admission::create([
                'patient_id' => $patient->id,
                'admitted_by' => $user->id,
            ]);

            return $admission;
        });

        $this->linkToJawdaMedical($admission);

        return $admission;
    }

    /**
     * Best-effort wrapper around registerInJawdaMedical(), used right after
     * an admission is created: a failure here (network error, clinic shift
     * closed, etc.) never blocks the admission itself. The patient simply
     * stays is_local_only and can be linked later through
     * PatientController::linkJawda().
     */
    private function linkToJawdaMedical(Admission $admission): void
    {
        if ($admission->patient === null || $admission->patient->jawda_patient_id !== null) {
            return;
        }

        try {
            $this->registerInJawdaMedical($admission);
        } catch (\Throwable $e) {
            Log::warning('Failed to link admission to Jawda Medical', [
                'admission_id' => $admission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Registers a matching patient in Jawda Medical, tagged with this
     * admission's id, so Jawda Medical can look the admission back up later
     * (e.g. for the patient's companion contact when recording a given
     * dose), and stores the resulting jawda_patient_id so both systems
     * share one patient identity. Throws on failure, so a manual retry
     * (PatientController::linkJawda()) can report it back to the caller.
     *
     * Skipped if the patient was already linked by an earlier admission:
     * Jawda Medical has no notion of "re-admission", so there is no way to
     * tag a further visit onto an already-registered patient there without
     * creating a duplicate.
     */
    public function registerInJawdaMedical(Admission $admission): void
    {
        $patient = $admission->patient;

        if ($patient === null || $patient->jawda_patient_id !== null) {
            return;
        }

        $medicalPatient = $this->jawdaMedicalClient->createPatient([
            'name' => $patient->name,
            'phone' => $patient->phone,
            'gender' => $patient->gender,
            'age_year' => $patient->age_year,
            'age_month' => $patient->age_month,
            'age_day' => $patient->age_day,
            'address' => $patient->address,
            'doctor_name' => $patient->admittingDoctor?->name,
            'admission_id' => $admission->id,
        ]);

        if (! isset($medicalPatient['id'])) {
            throw new \RuntimeException('Jawda Medical did not return a patient id.');
        }

        $patient->update([
            'jawda_patient_id' => $medicalPatient['id'],
            'is_local_only' => false,
        ]);
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
    /**
     * Moves an already-admitted, already-bedded patient to a different bed in one step
     * (e.g. out of a private room into a ward), freeing the old bed and occupying the new one.
     */
    public function transferBed(Admission $admission, int $newBedId): Admission
    {
        return DB::transaction(function () use ($admission, $newBedId) {
            $lockedAdmission = Admission::query()->whereKey($admission->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedAdmission->status !== 'admitted' || $lockedAdmission->bed_id === null) {
                throw ValidationException::withMessages([
                    'bed_id' => ['لا يمكن نقل السرير لهذا التنويم.'],
                ]);
            }

            $newBed = Bed::whereKey($newBedId)->lockForUpdate()->firstOrFail();

            if ($newBed->status !== 'available') {
                throw ValidationException::withMessages([
                    'bed_id' => ['السرير غير متاح للتنويم حالياً.'],
                ]);
            }

            $oldBed = Bed::whereKey($lockedAdmission->bed_id)->lockForUpdate()->firstOrFail();

            $lockedAdmission->update(['bed_id' => $newBed->id]);
            $newBed->update(['status' => 'occupied']);
            $oldBed->update(['status' => 'available']);

            return $lockedAdmission;
        });
    }

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
