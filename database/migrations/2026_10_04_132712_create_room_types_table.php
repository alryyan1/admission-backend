<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const LEGACY_NAMES = [
        'normal' => 'عادية',
        'vip' => 'خاصة',
        'operation' => 'غرفة عمليات',
        'ward' => 'عنبر',
    ];

    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $now = now();

        foreach (DB::table('rooms')->distinct()->pluck('room_type') as $code) {
            DB::table('room_types')->insertOrIgnore([
                'code' => $code,
                'name' => self::LEGACY_NAMES[$code] ?? $code,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
