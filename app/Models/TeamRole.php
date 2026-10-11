<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sort_order',
        'is_default_team_member',
    ];

    protected $casts = [
        'is_protected' => 'boolean',
        'is_default_team_member' => 'boolean',
    ];
}
