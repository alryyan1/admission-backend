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
        'watermark_path',
        'use_logo',
        'use_stamp',
        'use_watermark',
        'notify_referring_doctor_whatsapp',
        'show_patient_relative_fields',
        'show_insurance_fields',
    ];

    protected $casts = [
        'use_logo' => 'boolean',
        'use_stamp' => 'boolean',
        'use_watermark' => 'boolean',
        'notify_referring_doctor_whatsapp' => 'boolean',
        'show_patient_relative_fields' => 'boolean',
        'show_insurance_fields' => 'boolean',
    ];

    protected $appends = [
        'logo_url',
        'stamp_url',
        'watermark_url',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'use_logo' => true,
            'use_stamp' => true,
            'use_watermark' => false,
            'notify_referring_doctor_whatsapp' => true,
            'show_patient_relative_fields' => false,
            'show_insurance_fields' => true,
        ]);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function getStampUrlAttribute(): ?string
    {
        return $this->stamp_path ? Storage::disk('public')->url($this->stamp_path) : null;
    }

    public function getWatermarkUrlAttribute(): ?string
    {
        return $this->watermark_path ? Storage::disk('public')->url($this->watermark_path) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
