<?php

declare(strict_types=1);

namespace App\Modules\Kodefikasi\Services;

use App\Modules\Kodefikasi\Models\Rekening;
use App\Modules\Shared\Errors\KesalahanDomain;
use Illuminate\Support\Facades\DB;

final class PemetaanService
{
    /**
     * Rekening Pemda hasil pemetaan dan jenisnya untuk rekening BLUD pada tanggal tertentu.
     *
     * @return array{rekening_pemda_id: int, jenis_id: int, kode_jenis: string}
     */
    public function resolusi(int $rekeningBludId, ?\DateTimeInterface $tanggal = null): array
    {
        $tanggal ??= now();
        $baris = DB::selectOne(
            'SELECT p.rekening_pemda_id, r.jenis_id, j.kode AS kode_jenis
               FROM pemetaan_rekening p
               JOIN rekening r ON r.id = p.rekening_pemda_id
               JOIN rekening j ON j.id = r.jenis_id
              WHERE p.rekening_blud_id = ? AND p.berlaku @> ?::date',
            [$rekeningBludId, $tanggal->format('Y-m-d')]
        );

        if ($baris === null) {
            $kode = Rekening::query()->whereKey($rekeningBludId)->value('kode');
            throw new KesalahanDomain('REKENING_BELUM_DIPETAKAN',
                "Rekening {$kode} belum dipetakan ke rekening Pemda, sehingga belum dapat dianggarkan.",
                tindakan: ['label' => 'Buka pemetaan rekening', 'url' => '/kodefikasi/pemetaan/belum']);
        }

        return ['rekening_pemda_id' => (int) $baris->rekening_pemda_id, 'jenis_id' => (int) $baris->jenis_id, 'kode_jenis' => $baris->kode_jenis];
    }

    public function kodeJenis(int $jenisId): string
    {
        return (string) Rekening::query()->whereKey($jenisId)->value('kode');
    }
}
