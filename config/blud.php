<?php

/*
 * Nilai bawaan aturan BLUD. Nilai yang dapat diubah tanpa rilis disimpan di tabel
 * `konfigurasi` dan dibaca melalui App\Modules\Shared\Konfigurasi; berkas ini hanya
 * menjadi nilai awal (seed) dan fallback.
 */

return [
    'zona_waktu' => 'Asia/Jakarta',

    'revisi' => [
        // Kode jenis belanja (rekening Pemda tingkat 3) yang revisi seimbangnya langsung berlaku (PRD 7.7 butir 4).
        'jenis_berlaku_langsung' => ['5.1.02'],
    ],

    'pergeseran' => [
        'batas_pasangan_hari_kerja' => 5,
        'batas_pengesahan_hari_kerja' => 5,
        'persetujuan_otomatis_aktif' => true,
        // null = semua jenis; isi daftar kode jenis untuk membatasi (PRD K-25).
        'jenis_boleh_otomatis' => null,
        'pengingat_pasangan_hari_ke' => [4],
        'pengingat_pengesahan_hari_ke' => [3, 4],
    ],

    'standar_harga' => [
        'kecocokan' => [
            'bobot' => ['uraian' => 0.55, 'kode' => 0.15, 'satuan' => 0.15, 'harga' => 0.15],
            'ambang' => ['tinggi' => 0.70, 'sedang' => 0.45, 'rendah' => 0.30],
            'kandidat_maksimum' => 50,
            'similarity_minimum' => 0.25,
        ],
        'alasan_minimum_karakter' => 20,
    ],

    'persetujuan' => [
        'catatan_minimum_karakter' => 10,
        'step_up_menit' => 5,
    ],
];
