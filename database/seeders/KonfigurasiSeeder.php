<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Menyalin nilai bawaan config/blud.php ke tabel konfigurasi agar dapat diubah tanpa rilis. */
class KonfigurasiSeeder extends Seeder
{
    public function run(): void
    {
        $kunci = [
            'revisi.jenis_berlaku_langsung', 'pergeseran.batas_pasangan_hari_kerja', 'pergeseran.batas_pengesahan_hari_kerja',
            'pergeseran.persetujuan_otomatis_aktif', 'pergeseran.jenis_boleh_otomatis', 'pergeseran.pengingat_pasangan_hari_ke',
            'pergeseran.pengingat_pengesahan_hari_ke', 'standar_harga.kecocokan',
        ];
        foreach ($kunci as $k) {
            DB::table('konfigurasi')->insertOrIgnore(['kunci' => $k, 'nilai' => json_encode(config('blud.'.$k)), 'keterangan' => 'Nilai awal dari config/blud.php']);
        }
    }
}
