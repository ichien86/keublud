<?php

declare(strict_types=1);

namespace App\Modules\Anggaran\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Job harian: agregat pagu vs ledger dan total rincian vs pagu berlaku (TDD 4.7). */
final class RekonsiliasiPagu implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public readonly ?int $tahun = null) {}

    public function handle(): void
    {
        $tahun = $this->tahun ?? (int) now()->format('Y');
        $selisih = DB::select('SELECT * FROM fn_pagu_rekonsiliasi(?::smallint)', [$tahun]);

        // Rincian dalam paket yang menunggu pergeseran sengaja belum diterapkan, sehingga RINCIAN_VS_PAGU tetap harus nol.
        if ($selisih !== []) {
            Log::critical('Rekonsiliasi pagu menemukan selisih', ['tahun' => $tahun, 'selisih' => $selisih]);
        }

        DB::table('konfigurasi')->updateOrInsert(
            ['kunci' => 'job.rekonsiliasi_pagu.terakhir'],
            ['nilai' => json_encode(['waktu' => now()->toIso8601String(), 'tahun' => $tahun, 'jumlah_selisih' => count($selisih)]),
                'diubah_pada' => now()]
        );
    }
}
