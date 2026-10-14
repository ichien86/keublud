<?php

declare(strict_types=1);

namespace App\Modules\StandarHarga\Domain;

use App\Modules\Shared\Domain\Uang;

/**
 * Skor gabungan kecocokan usulan terhadap item standar yang ada (TDD 4.3):
 *   0.55 x kemiripan uraian + 0.15 x kode sama + 0.15 x satuan sama + 0.15 x kedekatan harga
 * Kemiripan uraian berasal dari pg_trgm similarity() (0..1) yang dihitung di database.
 */
final class SkorKecocokan
{
    public const TANPA = 'TANPA';

    public const RENDAH = 'RENDAH';

    public const SEDANG = 'SEDANG';

    public const TINGGI = 'TINGGI';

    /**
     * @param  array{uraian: float, kode: float, satuan: float, harga: float}  $bobot
     * @param  array{tinggi: float, sedang: float, rendah: float}  $ambang
     */
    public function __construct(
        private readonly array $bobot = ['uraian' => 0.55, 'kode' => 0.15, 'satuan' => 0.15, 'harga' => 0.15],
        private readonly array $ambang = ['tinggi' => 0.70, 'sedang' => 0.45, 'rendah' => 0.30],
    ) {}

    /**
     * @return array{skor: float, kategori: string, rincian: array<string, float>}
     */
    public function hitung(
        float $kemiripanUraian,
        ?string $kodeUsulan,
        ?string $kodeItem,
        string $satuanUsulan,
        string $satuanItem,
        Uang $hargaUsulan,
        Uang $hargaItem,
    ): array {
        $kode = ($kodeUsulan !== null && $kodeUsulan !== '' && $kodeUsulan === $kodeItem) ? 1.0 : 0.0;
        $satuan = NormalisasiUraian::satuan($satuanUsulan) === NormalisasiUraian::satuan($satuanItem) ? 1.0 : 0.0;
        $harga = $this->kedekatanHarga($hargaUsulan, $hargaItem);
        $uraian = max(0.0, min(1.0, $kemiripanUraian));

        $skor = $this->bobot['uraian'] * $uraian
            + $this->bobot['kode'] * $kode
            + $this->bobot['satuan'] * $satuan
            + $this->bobot['harga'] * $harga;
        $skor = round($skor, 4);

        return [
            'skor' => $skor,
            'kategori' => $this->kategori($skor),
            'rincian' => ['uraian' => round($uraian, 4), 'kode' => $kode, 'satuan' => $satuan, 'harga' => round($harga, 4)],
        ];
    }

    public function kategori(float $skor): string
    {
        return match (true) {
            $skor >= $this->ambang['tinggi'] => self::TINGGI,
            $skor >= $this->ambang['sedang'] => self::SEDANG,
            $skor >= $this->ambang['rendah'] => self::RENDAH,
            default => self::TANPA,
        };
    }

    /** Kategori terburuk (tertinggi) dari sekumpulan hasil; TANPA bila kosong. */
    public static function kategoriTertinggi(iterable $kategori): string
    {
        $urutan = [self::TANPA => 0, self::RENDAH => 1, self::SEDANG => 2, self::TINGGI => 3];
        $tertinggi = self::TANPA;
        foreach ($kategori as $k) {
            if ($urutan[$k] > $urutan[$tertinggi]) {
                $tertinggi = $k;
            }
        }

        return $tertinggi;
    }

    private function kedekatanHarga(Uang $a, Uang $b): float
    {
        $maks = max($a->sen, $b->sen);
        if ($maks <= 0) {
            return 0.0;
        }

        return 1.0 - min(1.0, abs($a->sen - $b->sen) / $maks);
    }
}
