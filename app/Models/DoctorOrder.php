<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id',
        'ordered_by',
        'order_text',
        'frequency',
        'route',
        'status',
    ];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function doses(): HasMany
    {
        return $this->hasMany(TreatmentDose::class);
    }
}
