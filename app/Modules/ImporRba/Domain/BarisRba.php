<?php

declare(strict_types=1);

namespace App\Modules\ImporRba\Domain;

use App\Modules\Shared\Domain\Uang;

/** Satu baris hasil baca lampiran RBA lama: komponen atau sub-komponen bernilai yang menjadi baris staging. */
final class BarisRba
{
    public int $baris = 0;

    public string $kodeBlud = '';

    public ?string $kodePemda = null;

    public string $komponen = '';

    /** Jalur sub-komponen (mis. "A. Pembangunan Gedung Rawat Inap › Konsultan Pengawas"); null bila komponen itu sendiri. */
    public ?string $rincian = null;

    public ?string $volume = null;

    public ?string $satuan = null;

    public ?Uang $harga = null;

    public Uang $jumlah;

    public ?string $label = null;

    /** Komponen bervolume "1 tahun/paket" yang wajib dipecah menjadi item standar harga. */
    public bool $gelondongan = false;

    /** @var list<string> */
    public array $petunjuk = [];

    /** @var list<string> */
    public array $peringatan = [];

    public function uraian(): string
    {
        return $this->rincian !== null ? $this->komponen.' — '.$this->rincian : $this->komponen;
    }
}
