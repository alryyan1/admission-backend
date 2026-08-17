<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalSign extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id',
        'recorded_by',
        'recorded_at',
        'temperature',
        'pulse',
        'respiration_rate',
        'blood_pressure',
        'oxygen_saturation',
        'notes',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'temperature' => 'decimal:1',
    ];

    protected static function booted(): void
    {
        static::creating(function (VitalSign $vitalSign) {
            $vitalSign->recorded_at ??= now();
        });
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
