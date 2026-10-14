<?php

declare(strict_types=1);

namespace App\Modules\Shared;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Konfigurasi yang dapat diubah tanpa rilis (tabel `konfigurasi`), dengan fallback ke config/blud.php.
 * Kunci memakai notasi titik yang sama dengan config('blud.*'), mis. 'pergeseran.batas_pasangan_hari_kerja'.
 */
final class Konfigurasi
{
    public function get(string $kunci, mixed $bawaan = null): mixed
    {
        $semua = Cache::remember('blud.konfigurasi', 300, static fn () => DB::table('konfigurasi')
            ->pluck('nilai', 'kunci')
            ->map(static fn (string $json) => json_decode($json, true))
            ->all());

        return array_key_exists($kunci, $semua) ? $semua[$kunci] : config('blud.'.$kunci, $bawaan);
    }

    public function set(string $kunci, mixed $nilai, ?int $userId, ?string $keterangan = null): void
    {
        DB::table('konfigurasi')->updateOrInsert(
            ['kunci' => $kunci],
            ['nilai' => json_encode($nilai), 'keterangan' => $keterangan, 'diubah_oleh' => $userId, 'diubah_pada' => now()],
        );
        Cache::forget('blud.konfigurasi');
    }
}
