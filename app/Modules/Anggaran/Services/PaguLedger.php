<?php

declare(strict_types=1);

namespace App\Modules\Anggaran\Services;

use App\Modules\Shared\Domain\Uang;
use App\Modules\Shared\Errors\KesalahanDomain;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalan aplikasi mengubah agregat pagu (TDD 4.1). Membungkus fn_pagu_catat.
 * Pemanggil wajib berada dalam DB::transaction() dan sudah mengunci baris pagu_jenis (Kunci::baris).
 */
final class PaguLedger
{
    public const PAGU = 'PAGU';

    public const REALISASI = 'REALISASI';

    public const KOMITMEN = 'KOMITMEN';

    public const TERCADANG = 'TERCADANG';

    public function catat(int $paguJenisId, string $komponen, Uang $nilai, string $sumberTipe, int $sumberId, ?string $keterangan = null): void
    {
        if ($nilai->adalahNol()) {
            return;
        }
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('PaguLedger::catat wajib dipanggil di dalam transaksi');
        }

        try {
            DB::select('SELECT fn_pagu_catat(?, ?, ?::numeric, ?, ?, ?)', [
                $paguJenisId, $komponen, $nilai->keDesimal(), $sumberTipe, $sumberId, $keterangan,
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'pagu_sisa_tidak_negatif')) {
                throw new KesalahanDomain('SISA_PAGU_TIDAK_CUKUP', 'Sisa pagu tidak mencukupi untuk perubahan ini.',
                    ['pagu_jenis_id' => $paguJenisId, 'komponen' => $komponen, 'nilai' => $nilai->keDesimal()], sebelumnya: $e);
            }
            throw KesalahanDomain::dariGalatDatabase($e) ?? $e;
        }
    }
}
