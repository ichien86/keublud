<?php

declare(strict_types=1);

namespace App\Modules\StandarHarga\Services;

use App\Modules\Shared\Domain\Uang;
use App\Modules\Shared\Konfigurasi;
use App\Modules\StandarHarga\Domain\SkorKecocokan;
use Illuminate\Support\Facades\DB;

/**
 * Pengecekan kecocokan usulan terhadap standar harga yang ada (PRD 7.4 butir 4, TDD 4.3).
 * Kandidat diambil dengan indeks GIN trigram, lalu diberi skor gabungan di PHP.
 */
final class StandarHargaMatcher
{
    public function __construct(private readonly Konfigurasi $konfigurasi) {}

    /**
     * @return list<array{item_id: int, sumber: string, kode_barang: ?string, uraian: string, spesifikasi: ?string,
     *                    satuan: string, harga: string, skor: float, kategori: string, rincian: array<string, float>}>
     */
    public function cari(int $tahun, string $uraian, ?string $spesifikasi, string $satuan, Uang $harga, ?string $kodeBarang = null, ?int $kecualiItemId = null): array
    {
        $cfg = (array) $this->konfigurasi->get('standar_harga.kecocokan');
        $skor = new SkorKecocokan($cfg['bobot'] ?? ['uraian' => 0.55, 'kode' => 0.15, 'satuan' => 0.15, 'harga' => 0.15],
            $cfg['ambang'] ?? ['tinggi' => 0.70, 'sedang' => 0.45, 'rendah' => 0.30]);
        $q = trim($uraian.' '.($spesifikasi ?? ''));

        DB::select("SELECT set_config('pg_trgm.similarity_threshold', ?, false)", [(string) ($cfg['similarity_minimum'] ?? 0.25)]);
        $kandidat = DB::select(
            "SELECT id, sumber, kode_barang, uraian, spesifikasi, satuan, harga::text AS harga,
                    similarity(uraian_norm, lower(unaccent_immutable(?))) AS kemiripan
               FROM standar_harga_item
              WHERE tahun = ? AND status = 'AKTIF' AND (?::bigint IS NULL OR id <> ?::bigint)
                AND (uraian_norm % lower(unaccent_immutable(?)) OR (?::text IS NOT NULL AND kode_barang = ?::text))
              ORDER BY kemiripan DESC
              LIMIT ?",
            [$q, $tahun, $kecualiItemId, $kecualiItemId, $q, $kodeBarang, $kodeBarang, (int) ($cfg['kandidat_maksimum'] ?? 50)]
        );

        $hasil = [];
        foreach ($kandidat as $k) {
            $h = $skor->hitung((float) $k->kemiripan, $kodeBarang, $k->kode_barang, $satuan, $k->satuan, $harga, Uang::dari($k->harga));
            if ($h['kategori'] === SkorKecocokan::TANPA) {
                continue;
            }
            $hasil[] = [
                'item_id' => (int) $k->id, 'sumber' => $k->sumber, 'kode_barang' => $k->kode_barang, 'uraian' => $k->uraian,
                'spesifikasi' => $k->spesifikasi, 'satuan' => $k->satuan, 'harga' => $k->harga,
                'skor' => $h['skor'], 'kategori' => $h['kategori'], 'rincian' => $h['rincian'],
            ];
        }
        usort($hasil, static fn ($a, $b) => $b['skor'] <=> $a['skor']);

        return $hasil;
    }
}
