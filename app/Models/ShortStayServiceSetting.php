<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ShortStayServiceSetting extends Model
{
    use LogsActivity;

    protected $fillable = [
        'enabled',
        'service_12h_id',
        'service_24h_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function service12h(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_12h_id');
    }

    public function service24h(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_24h_id');
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
