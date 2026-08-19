<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'jawda_patient_id',
        'name',
        'phone',
        'gender',
        'age_year',
        'age_month',
        'age_day',
        'address',
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
}
