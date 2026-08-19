<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationSupply extends Model
{
    use HasFactory;

    protected $fillable = [
        'operation_id',
        'name',
        'quantity',
        'unit',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }
}
