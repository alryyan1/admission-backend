<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestedService extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id',
        'requested_by',
        'name',
        'quantity',
        'unit_price',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    protected $appends = ['total_price'];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    protected function totalPrice(): Attribute
    {
        return Attribute::get(fn () => round($this->quantity * (float) $this->unit_price, 2));
    }
}
