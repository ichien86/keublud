<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Perhitungan hari kerja (PRD 7.8a butir 10, TDD 4.6).
 * Hari kerja = bukan Sabtu/Minggu dan tidak tercantum di kalender libur.
 * Hitungan dimulai dari hari kerja SETELAH tanggal mulai; batas = 23:59:59 WIB hari kerja ke-n (asumsi T-1).
 */
final class KalenderKerja
{
    private readonly DateTimeZone $zona;

    /** @var array<string, true> */
    private readonly array $libur;

    /** @param iterable<string> $tanggalLibur daftar 'Y-m-d' */
    public function __construct(iterable $tanggalLibur = [], string $zona = 'Asia/Jakarta')
    {
        $this->zona = new DateTimeZone($zona);
        $peta = [];
        foreach ($tanggalLibur as $t) {
            $peta[$t] = true;
        }
        $this->libur = $peta;
    }

    public function hariKerja(DateTimeInterface $tanggal): bool
    {
        $lokal = DateTimeImmutable::createFromInterface($tanggal)->setTimezone($this->zona);
        $hari = (int) $lokal->format('N');   // 6 = Sabtu, 7 = Minggu

        return $hari < 6 && ! isset($this->libur[$lokal->format('Y-m-d')]);
    }

    public function akhirHariKerjaKe(DateTimeInterface $mulai, int $n): DateTimeImmutable
    {
        if ($n < 1) {
            throw new \InvalidArgumentException('n minimal 1');
        }
        $tanggal = DateTimeImmutable::createFromInterface($mulai)->setTimezone($this->zona)->setTime(0, 0);
        $hitung = 0;
        $pengaman = 0;
        while ($hitung < $n) {
            $tanggal = $tanggal->modify('+1 day');
            if ($this->hariKerja($tanggal)) {
                $hitung++;
            }
            if (++$pengaman > 366) {
                throw new \RuntimeException('Kalender libur tidak wajar: tidak ditemukan hari kerja dalam setahun');
            }
        }

        return $tanggal->setTime(23, 59, 59);
    }

    /**
     * Hari kerja ke berapa suatu waktu terhadap tanggal mulai (0 = hari pengajuan atau sebelum hari kerja pertama).
     * Dipakai untuk pengingat hari ke-3/ke-4.
     */
    public function hariKerjaKeBerapa(DateTimeInterface $mulai, DateTimeInterface $sekarang): int
    {
        $tanggal = DateTimeImmutable::createFromInterface($mulai)->setTimezone($this->zona)->setTime(0, 0);
        $akhir = DateTimeImmutable::createFromInterface($sekarang)->setTimezone($this->zona)->setTime(0, 0);
        $hitung = 0;
        while ($tanggal < $akhir) {
            $tanggal = $tanggal->modify('+1 day');
            if ($this->hariKerja($tanggal)) {
                $hitung++;
            }
        }

        return $hitung;
    }
}
