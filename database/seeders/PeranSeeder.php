<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Permission dan peran awal (PRD bagian 6). Matriks final mengikuti K-5/K-26. */
class PeranSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    public const PERAN = [
        'administrator' => ['konfigurasi.kelola', 'kodefikasi.kelola', 'standar.impor_pemda', 'rba.impor'],
        'perencana' => ['standar.usul', 'standar.putuskan', 'revisi.setujui', 'pergeseran.sahkan', 'rba.konsolidasi', 'rba.impor', 'dokumen_anggaran.bentuk'],
        'pengusul_unit' => ['standar.usul', 'rba.susun', 'revisi.ajukan', 'pergeseran.ajukan'],
        'kepala_unit' => ['pergeseran.setujui_unit', 'rba.verifikasi_unit'],
        'verifikator' => ['rba.verifikasi'],
        'pejabat_keuangan' => ['dokumen_anggaran.setujui', 'sp3b.setujui'],
        'pimpinan_blud' => ['rba.tetapkan', 'dokumen_anggaran.sahkan', 'perubahan_rba.sahkan', 'ambang_batas.sahkan', 'sp3b.sahkan'],
        'auditor' => ['audit.baca'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (self::PERAN as $peran => $izin) {
            foreach ($izin as $i) {
                Permission::findOrCreate($i, 'web');
            }
            Role::findOrCreate($peran, 'web')->syncPermissions($izin);
        }
    }
}
