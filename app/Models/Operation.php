<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Operation extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id',
        'surgeon_id',
        'operation_room_id',
        'operation_number',
        'procedure_name',
        'scheduled_at',
        'started_at',
        'ended_at',
        'status',
        'notes',
        'cancellation_reason',
        'cancelled_at',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Operation $operation) {
            $operation->status ??= 'scheduled';
        });

        static::created(function (Operation $operation) {
            $operation->forceFill([
                'operation_number' => sprintf('OP-%s-%06d', now()->format('y'), $operation->id),
            ])->saveQuietly();
        });
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function surgeon(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'surgeon_id');
    }

    public function operationRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'operation_room_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
