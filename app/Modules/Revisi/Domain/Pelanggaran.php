<?php

declare(strict_types=1);

namespace App\Modules\Revisi\Domain;

final class Pelanggaran implements \JsonSerializable
{
    public function __construct(
        public readonly int $baris,
        public readonly string $kode,
        public readonly string $pesan,
    ) {}

    /** @return array{baris: int, kode: string, pesan: string} */
    public function jsonSerialize(): array
    {
        return ['baris' => $this->baris, 'kode' => $this->kode, 'pesan' => $this->pesan];
    }
}
