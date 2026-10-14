<?php

use App\Modules\Shared\Domain\KalenderKerja;

$wib = new DateTimeZone('Asia/Jakarta');

test('lima hari kerja dari Senin berakhir Senin berikutnya pukul 23:59:59', function () use ($wib) {
    $k = new KalenderKerja;
    $batas = $k->akhirHariKerjaKe(new DateTimeImmutable('2026-10-12 09:00', $wib), 5);   // Senin
    expect($batas->format('Y-m-d H:i:s'))->toBe('2026-10-19 23:59:59');
});

test('UAT-42 akhir pekan dan libur nasional tidak dihitung', function () use ($wib) {
    // Kamis 24 Des 2026 diajukan; 25 Des libur, 26-27 akhir pekan.
    $k = new KalenderKerja(['2026-12-25', '2027-01-01']);
    $batas = $k->akhirHariKerjaKe(new DateTimeImmutable('2026-12-24 10:00', $wib), 5);
    // 28, 29, 30, 31 Des, (1 Jan libur), 4 Jan
    expect($batas->format('Y-m-d'))->toBe('2027-01-04');
});

test('pengajuan pada hari Sabtu dihitung mulai Senin', function () use ($wib) {
    $k = new KalenderKerja;
    $batas = $k->akhirHariKerjaKe(new DateTimeImmutable('2026-10-17 10:00', $wib), 1);
    expect($batas->format('Y-m-d'))->toBe('2026-10-19');
});

test('waktu UTC dikonversi ke WIB sebelum dihitung', function () {
    $k = new KalenderKerja;
    // Jumat 23:30 UTC = Sabtu 06:30 WIB -> hari kerja pertama Senin
    $batas = $k->akhirHariKerjaKe(new DateTimeImmutable('2026-10-16 23:30', new DateTimeZone('UTC')), 1);
    expect($batas->format('Y-m-d'))->toBe('2026-10-19');
});

test('hari kerja ke berapa untuk pengingat', function () use ($wib) {
    $k = new KalenderKerja;
    $mulai = new DateTimeImmutable('2026-10-12 09:00', $wib);
    expect($k->hariKerjaKeBerapa($mulai, new DateTimeImmutable('2026-10-15 08:00', $wib)))->toBe(3);
    expect($k->hariKerjaKeBerapa($mulai, new DateTimeImmutable('2026-10-12 15:00', $wib)))->toBe(0);
});
