<?php

declare(strict_types=1);

namespace App\Modules\Revisi\Domain;

use App\Modules\Shared\Domain\Uang;

final class RincianBerlaku
{
    public function __construct(
        public readonly int $id,
        public readonly Uang $nilai,
        public readonly Uang $realisasi,
        public readonly Uang $komitmen,
        public readonly int $rowVersion,
        public readonly bool $terkunciPaketLain = false,
        public readonly bool $terkunciTransaksi = false,
    ) {}

    public function pemakaian(): Uang
    {
        return $this->realisasi->tambah($this->komitmen);
    }
}
