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
        'is_local_only',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'emergency_contact_address',
        'blood_type',
        'allergies',
        'chronic_diseases',
        'current_medications',
        'past_surgeries',
        'medical_history',
        'medical_notes',
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
