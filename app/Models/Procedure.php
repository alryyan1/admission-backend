<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Procedure extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name_ar',
        'name_en',
        'type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProcedureCategory::class, 'category_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class);
    }
}
