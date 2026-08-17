<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'ward_id',
        'room_number',
        'room_type',
        'capacity',
        'price_per_day',
        'is_short_stay',
        'price_12_hours',
        'price_24_hours',
        'status',
    ];

    protected $casts = [
        'price_per_day' => 'decimal:2',
        'is_short_stay' => 'boolean',
        'price_12_hours' => 'decimal:2',
        'price_24_hours' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class)->orderByRaw('CAST(bed_number AS UNSIGNED) ASC, bed_number ASC');
    }
}
