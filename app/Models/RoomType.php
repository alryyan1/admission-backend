<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RoomType extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['code', 'name'];

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'room_type', 'code');
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = 'type_'.Str::lower(Str::random(8));
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
