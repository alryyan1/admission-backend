<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NursingAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'nurse_id',
        'bed_id',
        'shift_start',
        'shift_end',
    ];

    protected $casts = [
        'shift_start' => 'datetime',
        'shift_end' => 'datetime',
    ];

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }
}
