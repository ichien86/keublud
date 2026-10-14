<?php

declare(strict_types=1);

namespace App\Modules\ImporRba\Domain;

use App\Modules\Shared\Domain\Uang;

final class HasilBacaRba
{
    public ?string $galat = null;

    /** @var list<string> judul kelompok nilai yang tersedia (mis. Penetapan, Perubahan) */
    public array $kelompok = [];

    public ?string $kelompokDipakai = null;

    /** @var array<string, array{kode: string, nama: string}> */
    public array $kepala = [];

    /** @var list<BarisRba> */
    public array $baris = [];

    /** @var array<string, Uang> total per rekening Pemda (tingkat 6) menurut file */
    public array $totalRekening = [];

    /** @var array<string, string> */
    public array $uraianRekening = [];

    /**
     * Kodefikasi yang tertulis di file, sebagai bahan melengkapi bagan rekening: [bagan][kode] => uraian, tingkat, pemda?.
     *
     * @var array<string, array<string, array{uraian: string, tingkat: int, pemda?: ?string}>>
     */
    public array $rekening = ['PEMDA' => [], 'BLUD' => []];

    /** @var list<array{baris: int, kode_blud: string, uraian: string}> komponen bernilai nol (dilewati) */
    public array $nol = [];

    /** @var list<string> */
    public array $peringatan = [];

    public static function gagal(string $pesan): self
    {
        $h = new self;
        $h->galat = $pesan;

        return $h;
    }

    /** @return array<string, Uang> jumlah baris terbaca per rekening Pemda */
    public function totalTerbaca(): array
    {
        $t = [];
        foreach ($this->baris as $b) {
            $k = $b->kodePemda ?? '-';
            $t[$k] = ($t[$k] ?? Uang::nol())->tambah($b->jumlah);
        }

        return $t;
    }

    /** @return list<array{kode: string, uraian: string, file: Uang, terbaca: Uang, cocok: bool}> */
    public function rekonsiliasi(): array
    {
        $terbaca = $this->totalTerbaca();
        $hasil = [];
        foreach ($this->totalRekening as $kode => $file) {
            $t = $terbaca[$kode] ?? Uang::nol();
            $hasil[] = ['kode' => $kode, 'uraian' => $this->uraianRekening[$kode] ?? '', 'file' => $file, 'terbaca' => $t, 'cocok' => $t->sama($file)];
        }

        return $hasil;
    }
}
