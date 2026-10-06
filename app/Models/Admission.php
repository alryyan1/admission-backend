<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Admission extends Model
{
    use HasFactory, LogsActivity;

    /**
     * How the patient came into the hospital, keyed by stored value.
     *
     * @var array<string, string>
     */
    public const ENTRY_TYPES = [
        'emergency' => 'طوارئ',
        'clinic_referral' => 'تحويل من عيادة',
        'hospital_transfer' => 'تحويل من مستشفى آخر',
        'scheduled' => 'دخول مجدول',
    ];

    public const ENTRY_TYPE_HOSPITAL_TRANSFER = 'hospital_transfer';

    protected $fillable = [
        'patient_id',
        'bed_id',
        'admitted_by',
        'discharged_by',
        'cancelled_by',
        'admission_number',
        'entry_type',
        'referring_hospital_name',
        'admission_date',
        'discharge_date',
        'status',
        'diagnosis',
        'admission_notes',
        'discharge_summary',
        'cancellation_reason',
        'cancelled_at',
    ];

    protected $casts = [
        'admission_date' => 'datetime',
        'discharge_date' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Admission $admission) {
            $admission->admission_date ??= now();
            $admission->status ??= 'admitted';
        });

        static::created(function (Admission $admission) {
            if ($admission->bed_id !== null) {
                $admission->bed()->update(['status' => 'occupied']);
            }

            $sequence = static::query()
                ->whereBetween('admission_date', [
                    $admission->admission_date->copy()->startOfDay(),
                    $admission->admission_date->copy()->endOfDay(),
                ])
                ->lockForUpdate()
                ->count();

            $admission->forceFill([
                'admission_number' => (string) $sequence,
            ])->saveQuietly();
        });

        static::updated(function (Admission $admission) {
            if (
                $admission->wasChanged('status')
                && in_array($admission->status, ['discharged', 'cancelled'], true)
                && $admission->bed_id !== null
            ) {
                $admission->bed()->update(['status' => 'available']);
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function admittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitted_by');
    }

    public function dischargedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discharged_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class);
    }

    public function doctorOrders(): HasMany
    {
        return $this->hasMany(DoctorOrder::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(AdmissionDeposit::class);
    }

    public function requestedServices(): HasMany
    {
        return $this->hasMany(RequestedService::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Billable nights for room charges: any partial 24-hour block counts as a full
     * night (ceil), with a 1-night minimum even for a same-day discharge.
     */
    public function nightsStayed(): int
    {
        $end = $this->discharge_date ?? now();
        $hours = $this->admission_date->diffInHours($end);

        return max(1, (int) ceil($hours / 24));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function assertMutable(User $user): void
    {
        if (in_array($this->status, ['discharged', 'cancelled'], true) && $user->role !== 'admin') {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن التعديل على تنويم مخرّج أو ملغى بالفعل.'],
            ]);
        }
    }
}
