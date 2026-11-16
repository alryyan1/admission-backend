<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShortStayAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id',
        'assessed_by',
        'blood_pressure',
        'oxygen_saturation',
        'pulse',
        'respiration_rate',
        'pmh',
        'chief_complaint',
        'plan',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
