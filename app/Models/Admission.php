<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Admission extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'bed_id',
        'admitting_doctor_id',
        'admitted_by',
        'discharged_by',
        'cancelled_by',
        'admission_number',
        'admission_type',
        'admission_date',
        'discharge_date',
        'admission_duration_hours',
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
            $admission->bed()->update(['status' => 'occupied']);
            $admission->forceFill([
                'admission_number' => sprintf('ADM-%s-%06d', now()->format('y'), $admission->id),
            ])->saveQuietly();
        });

        static::updated(function (Admission $admission) {
            if ($admission->wasChanged('status') && in_array($admission->status, ['discharged', 'cancelled'], true)) {
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

    public function admittingDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'admitting_doctor_id');
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

    public function nightsStayed(): int
    {
        $end = $this->discharge_date ?? now();
        $hours = $this->admission_date->diffInHours($end);

        return max(1, (int) ceil($hours / 24));
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
