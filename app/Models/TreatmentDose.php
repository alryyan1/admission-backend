<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentDose extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_order_id',
        'administered_by',
        'administered_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'administered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TreatmentDose $dose) {
            $dose->administered_at ??= now();
        });
    }

    public function doctorOrder(): BelongsTo
    {
        return $this->belongsTo(DoctorOrder::class);
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }
}
