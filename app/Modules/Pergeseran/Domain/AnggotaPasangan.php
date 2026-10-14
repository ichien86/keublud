<?php

declare(strict_types=1);

namespace App\Modules\Pergeseran\Domain;

use App\Modules\Shared\Domain\Uang;
use DateTimeImmutable;

final class AnggotaPasangan
{
    public function __construct(
        public readonly int $permintaanId,
        public readonly string $arah,          // TAMBAH | KURANG
        public readonly Uang $nilai,
        public readonly int $unitId,
        public readonly int $jenisId,
        public readonly int $sumberDanaId,
        public readonly int $tahun,
        public readonly string $status,
        public readonly DateTimeImmutable $batasPasangan,
        public readonly bool $memuatDiAtasStandar = false,
    ) {}
}
