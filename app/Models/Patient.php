<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Patient extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'jawda_patient_id',
        'name',
        'phone',
        'gender',
        'age_year',
        'age_month',
        'age_day',
        'address',
        'insurance_company_id',
        'insurance_card_number',
        'company_id',
        'guardian_relationship_id',
        'admitting_doctor_id',
        'referred_by_doctor_id',
        'is_local_only',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'emergency_contact_address',
    ];

    protected $casts = [
        'is_local_only' => 'boolean',
    ];

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function guardianRelationship(): BelongsTo
    {
        return $this->belongsTo(GuardianRelationship::class);
    }

    public function admittingDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'admitting_doctor_id');
    }

    public function referredByDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'referred_by_doctor_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
