<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->foreignId('procedure_id')->nullable()->after('operation_type')->constrained('procedures')->restrictOnDelete();
            $table->string('priority')->default('scheduled')->after('procedure_id');
            $table->text('diagnosis')->nullable()->after('priority');
            $table->unsignedInteger('expected_duration_minutes')->nullable()->after('diagnosis');
            $table->string('anesthesia_type')->nullable()->after('expected_duration_minutes');
            $table->unsignedInteger('requested_by_doctor_id')->nullable()->after('anesthesia_type');
        });

        $rows = DB::table('operations')->select('id', 'procedure_name', 'operation_type')->get();

        foreach ($rows as $row) {
            $name = trim((string) $row->procedure_name);
            if ($name === '') {
                continue;
            }

            $categoryId = null;
            $category = trim((string) $row->operation_type);
            if ($category !== '') {
                $categoryId = DB::table('procedure_categories')->where('name', $category)->value('id');
                if (! $categoryId) {
                    $categoryId = DB::table('procedure_categories')->insertGetId([
                        'name' => $category,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $procedureId = DB::table('procedures')->where('name_ar', $name)->value('id');
            if (! $procedureId) {
                $procedureId = DB::table('procedures')->insertGetId([
                    'category_id' => $categoryId,
                    'name_ar' => $name,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('operations')->where('id', $row->id)->update(['procedure_id' => $procedureId]);
        }

        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn(['procedure_name', 'operation_type']);
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->string('procedure_name')->nullable()->after('operation_room_id');
            $table->string('operation_type')->nullable()->after('procedure_name');
        });

        $rows = DB::table('operations')
            ->join('procedures', 'procedures.id', '=', 'operations.procedure_id')
            ->leftJoin('procedure_categories', 'procedure_categories.id', '=', 'procedures.category_id')
            ->select('operations.id', 'procedures.name_ar', 'procedure_categories.name as category_name')
            ->get();

        foreach ($rows as $row) {
            DB::table('operations')->where('id', $row->id)->update([
                'procedure_name' => $row->name_ar,
                'operation_type' => $row->category_name,
            ]);
        }

        Schema::table('operations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procedure_id');
            $table->dropColumn(['priority', 'diagnosis', 'expected_duration_minutes', 'anesthesia_type', 'requested_by_doctor_id']);
        });
    }
};
