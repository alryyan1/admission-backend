<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FacilitySetting extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'logo_path',
        'stamp_path',
        'use_logo',
        'use_stamp',
    ];

    protected $casts = [
        'use_logo' => 'boolean',
        'use_stamp' => 'boolean',
    ];

    protected $appends = [
        'logo_url',
        'stamp_url',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function getStampUrlAttribute(): ?string
    {
        return $this->stamp_path ? Storage::disk('public')->url($this->stamp_path) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
