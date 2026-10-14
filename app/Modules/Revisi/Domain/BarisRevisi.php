<?php

declare(strict_types=1);

namespace App\Modules\Revisi\Domain;

use App\Modules\Shared\Domain\Uang;
use InvalidArgumentException;

/** Satu baris perubahan dalam draf paket revisi. */
final class BarisRevisi
{
    public const TAMBAH = 'TAMBAH';

    public const UBAH = 'UBAH';

    public const HAPUS = 'HAPUS';

    public function __construct(
        public readonly string $aksi,
        public readonly ?int $rincianId,
        public readonly string $volume,
        public readonly Uang $harga,
        public readonly Uang $hargaStandar,
        public readonly ?int $rowVersion = null,
    ) {
        if (! in_array($aksi, [self::TAMBAH, self::UBAH, self::HAPUS], true)) {
            throw new InvalidArgumentException("Aksi tidak dikenal: {$aksi}");
        }
        if ($aksi === self::TAMBAH && $rincianId !== null) {
            throw new InvalidArgumentException('Baris TAMBAH tidak memiliki rincian asal');
        }
        if ($aksi !== self::TAMBAH && $rincianId === null) {
            throw new InvalidArgumentException("Baris {$aksi} wajib merujuk rincian");
        }
    }

    public function nilai(): Uang
    {
        return $this->aksi === self::HAPUS ? Uang::nol() : $this->harga->kaliVolume($this->volume);
    }
}
