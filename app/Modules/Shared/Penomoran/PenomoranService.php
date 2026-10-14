<?php

declare(strict_types=1);

namespace App\Modules\Shared\Penomoran;

use Illuminate\Support\Facades\DB;

/**
 * Penomoran dokumen di dalam transaksi bisnis yang sama (TDD 4.11), sehingga nomor tidak loncat bila transaksi gagal.
 * Token format: {NO:4} {KODE} {UNIT} {BULAN_ROMAWI} {TAHUN}
 */
final class PenomoranService
{
    private const ROMAWI = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /** @var array<string, string> format bawaan bila belum dikonfigurasi */
    private const BAWAAN = [
        'REVISI' => '{NO:4}/REV/{UNIT}/{BULAN_ROMAWI}/{TAHUN}',
        'PERMINTAAN' => '{NO:4}/PMT/{UNIT}/{BULAN_ROMAWI}/{TAHUN}',
        'PASANGAN' => '{NO:4}/PGS/{BULAN_ROMAWI}/{TAHUN}',
        'DOKUMEN_ANGGARAN' => '{NO:3}/DPA-BLUD/{TAHUN}',
        'PENETAPAN_RBA' => '{NO:3}/KEP-RBA/{TAHUN}',
    ];

    public function berikutnya(string $jenis, int $tahun, string $kodeUnit = '-', ?\DateTimeInterface $tanggal = null): string
    {
        $tanggal ??= now();

        DB::insert(
            'INSERT INTO nomor_dokumen (jenis, tahun, format) VALUES (?, ?, ?) ON CONFLICT (jenis, tahun) DO NOTHING',
            [$jenis, $tahun, self::BAWAAN[$jenis] ?? '{NO:4}/'.$jenis.'/{TAHUN}']
        );
        $baris = DB::selectOne(
            'UPDATE nomor_dokumen SET nomor_terakhir = nomor_terakhir + 1 WHERE jenis = ? AND tahun = ? RETURNING nomor_terakhir, format',
            [$jenis, $tahun]
        );

        return self::format($baris->format, (int) $baris->nomor_terakhir, $kodeUnit, $tahun, (int) $tanggal->format('n'));
    }

    public static function format(string $format, int $nomor, string $kodeUnit, int $tahun, int $bulan): string
    {
        $hasil = preg_replace_callback('/\{NO:(\d)\}/', static fn ($m) => str_pad((string) $nomor, (int) $m[1], '0', STR_PAD_LEFT), $format);

        return strtr((string) $hasil, [
            '{UNIT}' => $kodeUnit,
            '{TAHUN}' => (string) $tahun,
            '{BULAN_ROMAWI}' => self::ROMAWI[$bulan],
        ]);
    }
}
