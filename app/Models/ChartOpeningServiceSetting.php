<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ChartOpeningServiceSetting extends Model
{
    use LogsActivity;

    protected $fillable = [
        'service_id',
        'auto_add',
        'apply_to_short_stay',
    ];

    protected $casts = [
        'auto_add' => 'boolean',
        'apply_to_short_stay' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
