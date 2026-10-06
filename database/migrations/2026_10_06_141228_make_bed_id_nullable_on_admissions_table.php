<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Allows admissions to exist without a bed (patient registered before bed assignment).
     *
     * MySQL alters the column in place. SQLite (used by the test suite) cannot, so its table is rebuilt.
     * Schema::change() is avoided because it requires doctrine/dbal, which this app does not install.
     */
    public function up(): void
    {
        $this->setBedIdNullability(nullable: true);
    }

    public function down(): void
    {
        $this->setBedIdNullability(nullable: false);
    }

    private function setBedIdNullability(bool $nullable): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable($nullable);

            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE admissions MODIFY bed_id BIGINT UNSIGNED %s',
            $nullable ? 'NULL' : 'NOT NULL',
        ));
    }

    private function rebuildSqliteTable(bool $nullable): void
    {
        $createSql = DB::selectOne("select sql from sqlite_master where type = 'table' and name = 'admissions'")->sql;
        $indexSqlStatements = collect(DB::select(
            "select sql from sqlite_master where type = 'index' and tbl_name = 'admissions' and sql is not null",
        ))->pluck('sql');

        $from = $nullable ? '"bed_id" integer not null' : '"bed_id" integer';
        $to = $nullable ? '"bed_id" integer' : '"bed_id" integer not null';

        $rebuiltSql = str_replace(
            ['"admissions"', $from],
            ['"admissions_rebuilt"', $to],
            $createSql,
        );

        DB::statement($rebuiltSql);
        DB::statement('INSERT INTO "admissions_rebuilt" SELECT * FROM "admissions"');
        DB::statement('DROP TABLE "admissions"');
        DB::statement('ALTER TABLE "admissions_rebuilt" RENAME TO "admissions"');

        foreach ($indexSqlStatements as $indexSql) {
            DB::statement($indexSql);
        }
    }
};
