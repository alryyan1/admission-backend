<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntakeOutputRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id',
        'recorded_by',
        'recorded_at',
        'feeding',
        'iv_fluid',
        'maintenance',
        'urine_output',
        'insensible_losses',
        'total_intake',
        'total_output',
        'balance',
        'notes',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (IntakeOutputRecord $record) {
            $record->recorded_at ??= now();
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
