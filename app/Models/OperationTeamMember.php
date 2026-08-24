<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationTeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'operation_id',
        'doctor_id',
        'name',
        'role_id',
        'notes',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(TeamRole::class);
    }
}
