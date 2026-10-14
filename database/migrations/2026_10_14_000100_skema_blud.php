<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Skema inti BLUD ditulis sebagai SQL murni di database/sql/*.sql agar constraint,
 * trigger, dan fungsi ledger dapat diuji langsung (tests/sql/run.sh) tanpa Laravel.
 * Perubahan skema berikutnya dibuat sebagai berkas SQL baru + migrasi baru, bukan
 * mengedit berkas lama.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $berkas = [
        '001_extensions.sql',
        '010_master.sql',
        '020_kodefikasi.sql',
        '030_standar_harga.sql',
        '040_anggaran.sql',
        '050_revisi.sql',
        '060_pergeseran.sql',
        '070_workflow.sql',
        '080_audit.sql',
        '085_dokumen.sql',
    ];

    public function up(): void
    {
        foreach ($this->berkas as $nama) {
            DB::unprepared(file_get_contents(database_path('sql/'.$nama)));
        }
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS
              jadwal_perubahan, dokumen_anggaran_rencana_penarikan, rencana_penarikan,
              dokumen_anggaran_rincian, dokumen_anggaran, konfigurasi_dokumen,
              audit_log, persetujuan_aksi, persetujuan, workflow_langkah, workflow_definisi,
              pasangan_anggota, pasangan_pergeseran, permintaan_pergeseran,
              revisi_detail, revisi_paket, pagu_mutasi, pagu_jenis, rba_rincian, rba,
              usulan_kecocokan, standar_harga_item, usulan_standar_harga, usulan_standar_batch,
              standar_harga_impor, pemetaan_rekening, nomenklatur, rekening, kodefikasi_versi,
              lampiran, impor_baris_gagal, impor_batch, nomor_dokumen, delegasi, kalender_libur,
              konfigurasi, sumber_dana, pemetaan_label_unit, user_unit, unit_kerja
            CASCADE;
        SQL);
    }
};
