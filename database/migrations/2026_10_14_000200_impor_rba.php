<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* Impor RBA format lama (TDD 8.4). SQL baru, bukan mengubah berkas yang sudah dirilis. */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/090_impor_rba.sql')));
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS staging_rba_item, staging_rba, impor_rba_rekening, impor_rba CASCADE; DROP FUNCTION IF EXISTS fn_staging_item_harga();');
    }
};
