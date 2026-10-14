<?php

declare(strict_types=1);

namespace App\Modules\Pergeseran\Domain;

use App\Modules\Shared\Domain\Uang;
use DateTimeImmutable;

/**
 * Aturan pembentukan pasangan pergeseran (PRD 7.8a, TDD 4.5) dalam bentuk murni.
 * Database juga menjaga aturan yang sama (060_pergeseran.sql) sebagai pengaman terakhir.
 */
final class ValidatorPasangan
{
    /**
     * @param  list<AnggotaPasangan>  $anggota
     * @return array{valid: bool, galat: list<string>, total_tambah: Uang, total_kurang: Uang,
     *               memuat_di_atas_standar: bool, jenis_id: ?int, sumber_dana_id: ?int, tahun: ?int}
     */
    public function periksa(array $anggota, DateTimeImmutable $sekarang): array
    {
        $galat = [];
        $tambah = Uang::nol();
        $kurang = Uang::nol();
        $unitTambah = [];
        $unitKurang = [];
        $diAtasStandar = false;
        $acuan = $anggota[0] ?? null;
        $ids = [];

        if (count($anggota) < 2) {
            $galat[] = 'Pasangan memerlukan minimal satu permintaan tambah dan satu permintaan kurang.';
        }

        foreach ($anggota as $a) {
            if (isset($ids[$a->permintaanId])) {
                $galat[] = "Permintaan {$a->permintaanId} tercantum lebih dari sekali.";
            }
            $ids[$a->permintaanId] = true;

            if ($a->status !== 'TERBUKA') {
                $galat[] = "Permintaan {$a->permintaanId} berstatus {$a->status}, bukan TERBUKA.";
            }
            if ($a->batasPasangan < $sekarang) {
                $galat[] = "Permintaan {$a->permintaanId} telah melewati batas waktu pasangan.";
            }
            if ($acuan !== null && ($a->jenisId !== $acuan->jenisId || $a->sumberDanaId !== $acuan->sumberDanaId || $a->tahun !== $acuan->tahun)) {
                $galat[] = "Permintaan {$a->permintaanId} berbeda jenis belanja, sumber dana, atau tahun.";
            }

            if ($a->arah === 'TAMBAH') {
                $tambah = $tambah->tambah($a->nilai);
                $unitTambah[$a->unitId] = true;
            } elseif ($a->arah === 'KURANG') {
                $kurang = $kurang->tambah($a->nilai);
                $unitKurang[$a->unitId] = true;
            } else {
                $galat[] = "Arah permintaan {$a->permintaanId} tidak dikenal.";
            }
            $diAtasStandar = $diAtasStandar || $a->memuatDiAtasStandar;
        }

        if ($unitTambah === [] || $unitKurang === []) {
            $galat[] = 'Pasangan harus memuat sisi tambah dan sisi kurang.';
        }
        if (array_intersect_key($unitTambah, $unitKurang) !== []) {
            $galat[] = 'Unit yang sama tidak boleh berada di sisi tambah dan kurang.';
        }
        if (! $tambah->sama($kurang)) {
            $galat[] = sprintf('Total tambah %s tidak sama dengan total kurang %s.', $tambah->format(), $kurang->format());
        }

        return [
            'valid' => $galat === [],
            'galat' => array_values(array_unique($galat)),
            'total_tambah' => $tambah,
            'total_kurang' => $kurang,
            'memuat_di_atas_standar' => $diAtasStandar,
            'jenis_id' => $acuan?->jenisId,
            'sumber_dana_id' => $acuan?->sumberDanaId,
            'tahun' => $acuan?->tahun,
        ];
    }

    /**
     * Apakah pasangan boleh disetujui otomatis setelah batas waktu (PRD 7.8a butir 9, D-22, K-25).
     *
     * @param  list<string>|null  $jenisBolehOtomatis  kode jenis; null = semua
     */
    public static function bolehOtomatis(bool $memuatDiAtasStandar, string $kodeJenis, bool $fiturAktif, ?array $jenisBolehOtomatis): bool
    {
        if (! $fiturAktif || $memuatDiAtasStandar) {
            return false;
        }

        return $jenisBolehOtomatis === null || in_array($kodeJenis, $jenisBolehOtomatis, true);
    }
}
