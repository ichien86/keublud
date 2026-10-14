<?php

declare(strict_types=1);

namespace App\Modules\Revisi\Domain;

use App\Modules\Shared\Domain\Uang;

final class HasilRevisi implements \JsonSerializable
{
    /** @param list<Pelanggaran> $pelanggaran */
    public function __construct(
        public readonly Uang $totalSebelum,
        public readonly Uang $totalSesudah,
        public readonly Uang $paguBerlaku,
        public readonly Uang $selisih,
        public readonly string $status,
        public readonly bool $memuatDiAtasStandar,
        public readonly string $keputusan,
        public readonly ?string $alasanMenunggu,
        public readonly array $pelanggaran,
    ) {}

    public function arahPergeseran(): ?string
    {
        return match ($this->status) {
            KalkulatorRevisi::BERTAMBAH => 'TAMBAH',
            KalkulatorRevisi::BERKURANG => 'KURANG',
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'pagu_berlaku' => $this->paguBerlaku,
            'total_sebelum' => $this->totalSebelum,
            'total_sesudah' => $this->totalSesudah,
            'selisih' => $this->selisih,
            'status' => $this->status,
            'memuat_di_atas_standar' => $this->memuatDiAtasStandar,
            'keputusan' => $this->keputusan,
            'alasan_menunggu' => $this->alasanMenunggu,
            'pelanggaran' => $this->pelanggaran,
        ];
    }
}
