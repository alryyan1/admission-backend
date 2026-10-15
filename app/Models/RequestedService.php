<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestedService extends Model
{
    use HasFactory;

    public const AccommodationFeeName = 'رسوم الإقامة';

    protected $fillable = [
        'admission_id',
        'requested_by',
        'name',
        'quantity',
        'unit_price',
        'is_auto_added',
        'payment_method_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'is_auto_added' => 'boolean',
    ];

    protected $appends = ['total_price', 'is_paid'];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(AdmissionDeposit::class);
    }

    protected function totalPrice(): Attribute
    {
        return Attribute::get(fn () => round($this->quantity * (float) $this->unit_price, 2));
    }

    /**
     * Derived from whether a deposit is linked to this service, rather than stored
     * directly, so it can never drift out of sync with the deposit that backs it.
     */
    protected function isPaid(): Attribute
    {
        return Attribute::get(
            fn () => $this->relationLoaded('deposits') ? $this->deposits->isNotEmpty() : $this->deposits()->exists()
        );
    }
}
