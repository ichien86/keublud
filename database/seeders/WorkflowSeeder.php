<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Alur persetujuan awal. Langkah, permission, dan batas nilai disesuaikan dengan matriks kewenangan RSUD (PRD K-5).
 * boleh_otomatis hanya untuk langkah perencana pada pasangan pergeseran (PRD 7.8a butir 9).
 */
class WorkflowSeeder extends Seeder
{
    /** @var array<string, list<array{0: string, 1: string, 2: string, 3?: bool}>> nama, permission, cakupan, boleh_otomatis */
    public const ALUR = [
        'REVISI_DI_ATAS_STANDAR' => [['Persetujuan perencana', 'revisi.setujui', 'GLOBAL']],
        'REVISI_PERLU_PERSETUJUAN' => [['Persetujuan perencana', 'revisi.setujui', 'GLOBAL']],
        'PASANGAN_PERGESERAN' => [['Pengesahan perencana', 'pergeseran.sahkan', 'GLOBAL', true]],
        'USULAN_STANDAR' => [['Keputusan perencana', 'standar.putuskan', 'GLOBAL']],
        'RBA' => [
            ['Verifikasi', 'rba.verifikasi', 'GLOBAL'],
            ['Persetujuan internal', 'rba.konsolidasi', 'GLOBAL'],
            ['Penetapan Pimpinan BLUD', 'rba.tetapkan', 'GLOBAL'],
        ],
        'DOKUMEN_ANGGARAN' => [
            ['Persetujuan pejabat keuangan', 'dokumen_anggaran.setujui', 'GLOBAL'],
            ['Pengesahan Pimpinan BLUD', 'dokumen_anggaran.sahkan', 'GLOBAL'],
        ],
    ];

    public function run(): void
    {
        foreach (self::ALUR as $tipe => $langkah) {
            if (DB::table('workflow_definisi')->where('objek_tipe', $tipe)->exists()) {
                continue;
            }
            $def = DB::table('workflow_definisi')->insertGetId(['objek_tipe' => $tipe, 'versi' => 1, 'aktif' => true]);
            foreach ($langkah as $i => $l) {
                DB::table('workflow_langkah')->insert([
                    'definisi_id' => $def, 'urutan' => $i + 1, 'nama' => $l[0], 'permission' => $l[1],
                    'cakupan' => $l[2], 'boleh_otomatis' => $l[3] ?? false,
                ]);
            }
        }
    }
}
