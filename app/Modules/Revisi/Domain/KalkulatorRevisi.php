<?php

declare(strict_types=1);

namespace App\Modules\Revisi\Domain;

use App\Modules\Shared\Domain\Uang;

/**
 * Perhitungan murni untuk paket revisi rincian (PRD 7.7, TDD 4.4):
 * indikator status pagu, pelanggaran per baris, dan keputusan saat menyimpan.
 * Tidak menyentuh database; service memberi data yang sudah terkunci.
 */
final class KalkulatorRevisi
{
    public const SEIMBANG = 'SEIMBANG';
    public const BERTAMBAH = 'BERTAMBAH';
    public const BERKURANG = 'BERKURANG';

    public const KEPUTUSAN_BERLAKU = 'BERLAKU';
    public const KEPUTUSAN_MENUNGGU_PERENCANA = 'MENUNGGU_PERENCANA';
    public const KEPUTUSAN_PERLU_PERGESERAN = 'PERLU_PERGESERAN';
    public const KEPUTUSAN_DITOLAK = 'DITOLAK';

    /**
     * @param list<RincianBerlaku> $rincian rincian aktif pada pagu jenis ini
     * @param list<BarisRevisi> $draf perubahan dalam sesi edit
     */
    public function hitung(Uang $paguBerlaku, array $rincian, array $draf, bool $jenisBerlakuLangsung): HasilRevisi
    {
        $peta = [];
        foreach ($rincian as $r) {
            $peta[$r->id] = $r;
        }

        $pelanggaran = [];
        $disentuh = [];
        $nilaiDraf = Uang::nol();
        $diAtasStandar = false;

        foreach ($draf as $i => $b) {
            if ($b->aksi !== BarisRevisi::TAMBAH) {
                $asal = $peta[$b->rincianId] ?? null;
                if ($asal === null) {
                    $pelanggaran[] = new Pelanggaran($i, 'RINCIAN_TIDAK_DITEMUKAN', 'Rincian tidak ditemukan atau tidak aktif pada pagu ini.');

                    continue;
                }
                if (isset($disentuh[$b->rincianId])) {
                    $pelanggaran[] = new Pelanggaran($i, 'RINCIAN_GANDA', 'Rincian yang sama diubah lebih dari sekali dalam satu paket.');

                    continue;
                }
                $disentuh[$b->rincianId] = true;

                if ($asal->terkunciPaketLain) {
                    $pelanggaran[] = new Pelanggaran($i, 'RINCIAN_TERKUNCI', 'Rincian sedang berada dalam paket revisi lain yang menunggu.');
                }
                if ($asal->terkunciTransaksi) {
                    $pelanggaran[] = new Pelanggaran($i, 'RINCIAN_DIPAKAI_TRANSAKSI', 'Rincian sedang dipakai transaksi yang diajukan.');
                }
                if ($b->rowVersion !== null && $b->rowVersion !== $asal->rowVersion) {
                    $pelanggaran[] = new Pelanggaran($i, 'KONFLIK_VERSI', 'Rincian telah diubah orang lain sejak draf dibuat. Muat ulang rincian ini.');
                }
            }

            if ($b->aksi === BarisRevisi::HAPUS) {
                $asal = $peta[$b->rincianId] ?? null;
                if ($asal !== null && $asal->pemakaian()->positif()) {
                    $pelanggaran[] = new Pelanggaran($i, 'DI_BAWAH_PEMAKAIAN',
                        'Rincian yang sudah memiliki realisasi atau komitmen tidak dapat dihapus.');
                }

                continue;
            }

            $nilai = $b->nilai();
            $nilaiDraf = $nilaiDraf->tambah($nilai);

            if ($b->harga->lebihBesar($b->hargaStandar)) {
                $diAtasStandar = true;
            }

            if ($b->aksi === BarisRevisi::UBAH) {
                $asal = $peta[$b->rincianId] ?? null;
                if ($asal !== null && $nilai->lebihKecil($asal->pemakaian())) {
                    $pelanggaran[] = new Pelanggaran($i, 'DI_BAWAH_PEMAKAIAN', sprintf(
                        'Nilai baru %s lebih kecil dari realisasi + komitmen %s.',
                        $nilai->format(), $asal->pemakaian()->format()
                    ));
                }
            }
        }

        $nilaiTetap = Uang::jumlah(array_map(
            static fn (RincianBerlaku $r) => $r->nilai,
            array_values(array_filter($rincian, static fn (RincianBerlaku $r) => ! isset($disentuh[$r->id])))
        ));
        $totalSebelum = Uang::jumlah(array_map(static fn (RincianBerlaku $r) => $r->nilai, $rincian));
        $totalSesudah = $nilaiTetap->tambah($nilaiDraf);
        $selisih = $totalSesudah->kurang($paguBerlaku);

        $status = match (true) {
            $selisih->adalahNol() => self::SEIMBANG,
            $selisih->positif() => self::BERTAMBAH,
            default => self::BERKURANG,
        };

        $keputusan = match (true) {
            $pelanggaran !== [] => self::KEPUTUSAN_DITOLAK,
            $status !== self::SEIMBANG => self::KEPUTUSAN_PERLU_PERGESERAN,
            $diAtasStandar, ! $jenisBerlakuLangsung => self::KEPUTUSAN_MENUNGGU_PERENCANA,
            default => self::KEPUTUSAN_BERLAKU,
        };

        $alasanMenunggu = null;
        if ($keputusan === self::KEPUTUSAN_MENUNGGU_PERENCANA) {
            $alasanMenunggu = $diAtasStandar ? 'DI_ATAS_STANDAR' : 'JENIS_PERLU_PERSETUJUAN';
        }

        return new HasilRevisi(
            totalSebelum: $totalSebelum,
            totalSesudah: $totalSesudah,
            paguBerlaku: $paguBerlaku,
            selisih: $selisih,
            status: $status,
            memuatDiAtasStandar: $diAtasStandar,
            keputusan: $keputusan,
            alasanMenunggu: $alasanMenunggu,
            pelanggaran: $pelanggaran,
        );
    }
}
