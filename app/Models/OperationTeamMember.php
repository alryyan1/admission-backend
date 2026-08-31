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
        'entitlement_amount',
        'payment_method_id',
        'entitlement_paid_at',
    ];

    protected $casts = [
        'entitlement_amount' => 'decimal:2',
        'entitlement_paid_at' => 'date:Y-m-d',
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

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
