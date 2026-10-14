<?php

declare(strict_types=1);

namespace App\Modules\Pergeseran\Jobs;

use App\Modules\Pergeseran\Models\PasanganPergeseran;
use App\Modules\Pergeseran\Models\PermintaanPergeseran;
use App\Modules\Pergeseran\Services\PasanganService;
use App\Modules\Pergeseran\Services\PermintaanService;
use App\Modules\Shared\Audit\AuditService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tiap 15 menit (TDD 4.7):
 *  - permintaan TERBUKA yang lewat batas pasangan -> DITOLAK_OTOMATIS (UAT-40, UAT-41);
 *  - pasangan yang masih MENUNGGU_UNIT padahal batas salah satu anggotanya lewat -> dibubarkan.
 * Idempoten: status diperiksa ulang di dalam transaksi terkunci.
 */
final class ProsesBatasPasangan implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function handle(PermintaanService $permintaan, PasanganService $pasangan): void
    {
        PermintaanPergeseran::query()->where('status', 'TERBUKA')->where('batas_pasangan', '<', now())
            ->orderBy('id')->pluck('id')
            ->each(function (int $id) use ($permintaan) {
                try {
                    DB::transaction(function () use ($id, $permintaan) {
                        AuditService::sebagaiSistem();
                        $m = $permintaan->kunci(PermintaanPergeseran::query()->findOrFail($id));
                        if ($m->status === 'TERBUKA' && $m->batas_pasangan->lessThan(now())) {
                            $permintaan->akhiri($m, 'DITOLAK_OTOMATIS', 'Pasangan tidak terbentuk sampai batas waktu');
                        }
                    }, attempts: 3);
                } catch (\Throwable $e) {
                    Log::error('Gagal memproses batas permintaan', ['id' => $id, 'galat' => $e->getMessage()]);
                }
            });

        PasanganPergeseran::query()->where('status', 'MENUNGGU_UNIT')
            ->whereExists(fn ($q) => $q->from('pasangan_anggota as a')->join('permintaan_pergeseran as m', 'm.id', '=', 'a.permintaan_id')
                ->whereColumn('a.pasangan_id', 'pasangan_pergeseran.id')->where('a.aktif', true)->where('m.batas_pasangan', '<', now()))
            ->orderBy('id')->get()
            ->each(function (PasanganPergeseran $p) use ($pasangan) {
                try {
                    DB::transaction(function () use ($p, $pasangan) {
                        AuditService::sebagaiSistem();
                        $p = PasanganPergeseran::query()->whereKey($p->id)->lockForUpdate()->first();
                        if ($p?->status === 'MENUNGGU_UNIT') {
                            $pasangan->bubarkan($p, 'DITOLAK', 'Persetujuan unit tidak lengkap sampai batas waktu pasangan');
                        }
                    }, attempts: 3);
                } catch (\Throwable $e) {
                    Log::error('Gagal memproses batas pasangan', ['id' => $p->id, 'galat' => $e->getMessage()]);
                }
            });

        DB::table('konfigurasi')->updateOrInsert(['kunci' => 'job.batas_pasangan.terakhir'],
            ['nilai' => json_encode(['waktu' => now()->toIso8601String()]), 'diubah_pada' => now()]);
    }
}
