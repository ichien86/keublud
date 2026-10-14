<?php

declare(strict_types=1);

namespace App\Modules\Shared\Errors;

use RuntimeException;
use Throwable;

/**
 * Kesalahan aturan bisnis dengan format respons seragam (TDD 6.1):
 * { kode, pesan, tindakan: {label, url}, detail }
 */
class KesalahanDomain extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $detail
     * @param  array{label: string, url: string}|null  $tindakan
     */
    public function __construct(
        public readonly string $kode,
        string $pesan,
        public readonly array $detail = [],
        public readonly ?array $tindakan = null,
        private readonly int $statusHttp = 422,
        ?Throwable $sebelumnya = null,
    ) {
        parent::__construct($pesan, 0, $sebelumnya);
    }

    public function status(): int
    {
        return $this->statusHttp;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'kode' => $this->kode,
            'pesan' => $this->getMessage(),
            'tindakan' => $this->tindakan,
            'detail' => $this->detail ?: null,
        ], static fn ($v) => $v !== null);
    }

    /** Mengubah galat PostgreSQL dari trigger (pesan "KODE: ...") menjadi KesalahanDomain bila dikenali. */
    public static function dariGalatDatabase(Throwable $e): ?self
    {
        $pesan = $e->getMessage();
        if (preg_match('/ERROR:\s+([A-Z_]{6,}):\s*(.+?)(?:\s+\(|$|\n)/s', $pesan, $m)) {
            return new self($m[1], trim($m[2]), sebelumnya: $e);
        }
        foreach (['pagu_sisa_tidak_negatif' => 'SISA_PAGU_TIDAK_CUKUP', 'rincian_tidak_di_bawah_pemakaian' => 'DI_BAWAH_PEMAKAIAN',
            'rincian_harga_sah' => 'HARGA_DI_ATAS_STANDAR', 'pasangan_seimbang' => 'PASANGAN_TIDAK_SEIMBANG',
            'anggota_unik_aktif' => 'PERMINTAAN_SUDAH_BERPASANGAN'] as $constraint => $kode) {
            if (str_contains($pesan, $constraint)) {
                return new self($kode, 'Data ditolak oleh aturan basis data ('.$constraint.').', sebelumnya: $e);
            }
        }

        return null;
    }
}
