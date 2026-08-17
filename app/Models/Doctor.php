<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'jawda_doctor_id',
        'name',
        'specialist',
    ];

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class, 'admitting_doctor_id');
    }
}
