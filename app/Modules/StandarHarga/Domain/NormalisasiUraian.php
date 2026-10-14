<?php

declare(strict_types=1);

namespace App\Modules\StandarHarga\Domain;

/**
 * Normalisasi uraian dan satuan untuk pengecekan kecocokan standar harga (TDD 4.3).
 * Hasil normalisasi uraian juga dipakai sebagai kueri trigram ke PostgreSQL.
 */
final class NormalisasiUraian
{
    /** @var array<string, string> sinonim satuan -> bentuk baku */
    private const SATUAN = [
        'gr' => 'gram', 'g' => 'gram', 'gram' => 'gram', 'grm' => 'gram', 'gsm' => 'gram',
        'kg' => 'kg', 'kilogram' => 'kg',
        'ltr' => 'liter', 'l' => 'liter', 'liter' => 'liter', 'lt' => 'liter',
        'ml' => 'ml', 'cc' => 'ml',
        'bh' => 'buah', 'buah' => 'buah', 'pcs' => 'buah', 'pc' => 'buah', 'biji' => 'buah',
        'unit' => 'unit', 'set' => 'set', 'paket' => 'paket', 'pkt' => 'paket',
        'rim' => 'rim', 'box' => 'box', 'dus' => 'box', 'kotak' => 'box', 'ktk' => 'box',
        'tab' => 'tablet', 'tablet' => 'tablet', 'tbl' => 'tablet',
        'btl' => 'botol', 'botol' => 'botol', 'vial' => 'vial', 'amp' => 'ampul', 'ampul' => 'ampul',
        'bln' => 'bulan', 'bulan' => 'bulan', 'thn' => 'tahun', 'tahun' => 'tahun',
        'org' => 'orang', 'orang' => 'orang', 'oh' => 'orang hari', 'kegiatan' => 'kegiatan', 'keg' => 'kegiatan',
        'lbr' => 'lembar', 'lembar' => 'lembar', 'kit' => 'kit', 'roll' => 'rol', 'rol' => 'rol',
    ];

    private const KATA_UMUM = ['dan', 'untuk', 'ukuran', 'uk', 'type', 'tipe', 'jenis', 'merk', 'dengan', 'atau', 'yang'];

    public static function satuan(string $satuan): string
    {
        $s = mb_strtolower(trim($satuan));
        $s = preg_replace('/[^\p{L}\p{N} ]+/u', '', $s) ?? $s;

        return self::SATUAN[$s] ?? $s;
    }

    public static function uraian(string $uraian, ?string $spesifikasi = null): string
    {
        $teks = mb_strtolower(trim($uraian.' '.($spesifikasi ?? '')));
        $teks = self::hapusAksen($teks);
        $teks = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $teks) ?? $teks;

        // Satukan angka dengan satuannya: "70 gr" -> "70gram", "500 mg" -> "500mg"
        $teks = preg_replace_callback(
            '/(\d+(?:[.,]\d+)?)\s*(gr|g|gram|grm|gsm|kg|ml|cc|l|ltr|lt|mg|mm|cm|m|inch|in)\b/u',
            static function (array $m): string {
                $satuan = self::SATUAN[$m[2]] ?? $m[2];

                return str_replace(',', '.', $m[1]).$satuan;
            },
            $teks
        ) ?? $teks;

        $kata = array_filter(
            explode(' ', $teks),
            static fn (string $k): bool => $k !== '' && ! in_array($k, self::KATA_UMUM, true)
        );

        return implode(' ', $kata);
    }

    private static function hapusAksen(string $teks): string
    {
        if (class_exists(\Normalizer::class)) {
            $n = \Normalizer::normalize($teks, \Normalizer::FORM_D);
            if ($n !== false) {
                return preg_replace('/\p{Mn}+/u', '', $n) ?? $teks;
            }
        }

        return $teks;
    }
}
