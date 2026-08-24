<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Operation extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'admission_id',
        'surgeon_id',
        'operation_room_id',
        'operation_number',
        'procedure_id',
        'priority',
        'diagnosis',
        'expected_duration_minutes',
        'anesthesia_type',
        'requested_by_doctor_id',
        'scheduled_at',
        'started_at',
        'ended_at',
        'status',
        'notes',
        'consent_obtained',
        'fasting_confirmed',
        'site_marked',
        'preop_vitals_checked',
        'preop_notes',
        'prepared_at',
        'prepared_by',
        'findings',
        'complications',
        'blood_loss_ml',
        'outcome',
        'report_notes',
        'cancellation_reason',
        'cancelled_at',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'prepared_at' => 'datetime',
        'consent_obtained' => 'boolean',
        'fasting_confirmed' => 'boolean',
        'site_marked' => 'boolean',
        'preop_vitals_checked' => 'boolean',
        'blood_loss_ml' => 'integer',
        'expected_duration_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Operation $operation) {
            $operation->status ??= 'scheduled';
        });

        static::created(function (Operation $operation) {
            $operation->forceFill([
                'operation_number' => sprintf('OP-%s-%06d', now()->format('y'), $operation->id),
            ])->saveQuietly();
        });
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function surgeon(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'surgeon_id');
    }

    public function requestedByDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'requested_by_doctor_id');
    }

    public function operationRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'operation_room_id');
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(OperationTeamMember::class);
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(OperationSupply::class);
    }

    public function isPrepared(): bool
    {
        return $this->consent_obtained
            && $this->fasting_confirmed
            && $this->site_marked
            && $this->preop_vitals_checked;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
