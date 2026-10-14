<?php

declare(strict_types=1);

namespace App\Modules\Anggaran\Services;

use App\Modules\Anggaran\Models\PaguJenis;
use App\Modules\Anggaran\Models\Rba;
use App\Modules\Shared\Domain\Uang;
use App\Modules\Shared\Errors\KesalahanDomain;
use Illuminate\Support\Facades\DB;

/** Penetapan RBA definitif dan pembentukan pagu jenis melalui ledger (PRD 7.5–7.6, TDD 4.1). */
final class PenetapanRbaService
{
    public function __construct(private readonly PaguLedger $ledger) {}

    public function tetapkanDefinitif(Rba $rba, string $nomorPengesahan, \DateTimeInterface $tanggal): Rba
    {
        return DB::transaction(function () use ($rba, $nomorPengesahan, $tanggal) {
            /** @var Rba $rba */
            $rba = Rba::query()->whereKey($rba->id)->lockForUpdate()->firstOrFail();
            if ($rba->status !== 'MENUNGGU_PENETAPAN') {
                throw new KesalahanDomain('STATUS_TIDAK_SESUAI', "RBA berstatus {$rba->status}; hanya RBA yang menunggu penetapan dapat ditetapkan.");
            }
            if (Rba::definitif($rba->tahun) !== null) {
                throw new KesalahanDomain('SUDAH_ADA_DEFINITIF', "Tahun {$rba->tahun} sudah memiliki RBA definitif. Perubahan dilakukan melalui perubahan RBA.");
            }

            $rba->forceFill([
                'status' => 'DEFINITIF', 'jenis_versi' => 'DEFINITIF',
                'nomor_pengesahan' => $nomorPengesahan, 'tanggal_pengesahan' => $tanggal->format('Y-m-d'),
            ])->save();

            $total = DB::select(
                'SELECT unit_id, jenis_id, sumber_dana_id, SUM(nilai) AS total
                   FROM rba_rincian WHERE rba_id = ? AND aktif GROUP BY unit_id, jenis_id, sumber_dana_id ORDER BY 1, 2, 3',
                [$rba->id]
            );

            foreach ($total as $t) {
                $pagu = PaguJenis::query()->forceCreate([
                    'tahun' => $rba->tahun, 'unit_id' => $t->unit_id, 'jenis_id' => $t->jenis_id,
                    'sumber_dana_id' => $t->sumber_dana_id, 'pagu_baseline' => $t->total,
                ]);
                $this->ledger->catat($pagu->id, PaguLedger::PAGU, Uang::dari((string) $t->total), 'RBA_DEFINITIF', $rba->id);
            }

            return $rba->refresh();
        }, attempts: 3);
    }
}
