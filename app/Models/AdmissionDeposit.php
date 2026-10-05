<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionDeposit extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id',
        'paid_by',
        'amount',
        'payment_method_id',
        'comment',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * Appended alongside the raw `paid_by` foreign key, which already holds
     * the user id — a `paidBy` relation key would collide with it when
     * serialized (both snake-case to `paid_by`).
     */
    protected $appends = [
        'paid_by_name',
    ];

    protected static function booted(): void
    {
        static::creating(function (AdmissionDeposit $deposit) {
            $deposit->paid_at ??= now();
        });
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function getPaidByNameAttribute(): ?string
    {
        return $this->paidBy?->name;
    }
}
