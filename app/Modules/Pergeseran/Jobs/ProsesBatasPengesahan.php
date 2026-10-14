<?php

declare(strict_types=1);

namespace App\Modules\Pergeseran\Jobs;

use App\Modules\Pergeseran\Models\PasanganPergeseran;
use App\Modules\Pergeseran\Services\PasanganService;
use App\Modules\Shared\Notifikasi\NotifikasiBlud;
use App\Modules\Shared\Notifikasi\Penerima;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Tiap 15 menit: pasangan MENUNGGU_PERENCANA yang lewat batas pengesahan (PRD 7.8a butir 9; TDD 4.7–4.8).
 *  - boleh otomatis (tanpa harga di atas standar, jenis diizinkan) -> sahkan oleh sistem, didahului validasi ulang;
 *  - tidak boleh otomatis -> tetap menunggu, pengingat dikirim sekali per hari kerja (UAT-45).
 */
final class ProsesBatasPengesahan implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function handle(PasanganService $layanan, Penerima $penerima): void
    {
        PasanganPergeseran::query()->where('status', 'MENUNGGU_PERENCANA')->where('batas_pengesahan', '<', now())
            ->orderBy('id')->get()
            ->each(function (PasanganPergeseran $p) use ($layanan, $penerima) {
                try {
                    if ($layanan->bolehOtomatis($p)) {
                        $layanan->sahkan($p, null);

                        return;
                    }
                    $kunci = 'pengingat-lewat-batas:'.$p->id.':'.now()->format('Y-m-d');
                    if (Cache::add($kunci, true, now()->addDay())) {
                        Notification::send($penerima->denganPermission('pergeseran.sahkan'), new NotifikasiBlud(
                            'PASANGAN_LEWAT_BATAS', "Pasangan {$p->nomor} melewati batas pengesahan",
                            'Pasangan ini memuat harga di atas standar atau jenis belanja yang tidak boleh disetujui otomatis, sehingga wajib diputuskan perencana.',
                            "/pergeseran/pasangan/{$p->uuid}", true));
                    }
                } catch (\Throwable $e) {
                    Log::error('Gagal memproses batas pengesahan', ['id' => $p->id, 'galat' => $e->getMessage()]);
                }
            });

        DB::table('konfigurasi')->updateOrInsert(['kunci' => 'job.batas_pengesahan.terakhir'],
            ['nilai' => json_encode(['waktu' => now()->toIso8601String()]), 'diubah_pada' => now()]);
    }
}
