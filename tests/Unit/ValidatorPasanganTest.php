<?php

use App\Modules\Pergeseran\Domain\AnggotaPasangan;
use App\Modules\Pergeseran\Domain\ValidatorPasangan;
use App\Modules\Shared\Domain\Uang;

function anggota(int $id, string $arah, string $nilai, int $unit, int $jenis = 3, bool $diAtas = false, string $status = 'TERBUKA'): AnggotaPasangan
{
    return new AnggotaPasangan($id, $arah, Uang::dari($nilai), $unit, $jenis, 1, 2026, $status,
        new DateTimeImmutable('2026-10-20 23:59:59'), $diAtas);
}

$v = new ValidatorPasangan;
$kini = new DateTimeImmutable('2026-10-14 10:00');

test('UAT-37 pasangan tambah dan kurang seimbang valid', function () use ($v, $kini) {
    $h = $v->periksa([anggota(1, 'TAMBAH', '5000000', 1), anggota(2, 'KURANG', '5000000', 2)], $kini);
    expect($h['valid'])->toBeTrue();
});

test('UAT-38 satu-ke-banyak seimbang valid', function () use ($v, $kini) {
    $h = $v->periksa([anggota(1, 'TAMBAH', '5000000', 1), anggota(2, 'KURANG', '3000000', 2), anggota(3, 'KURANG', '2000000', 3)], $kini);
    expect($h['valid'])->toBeTrue();
    expect($h['total_tambah']->keDesimal())->toBe('5000000.00');
});

test('UAT-39 total tidak sama tidak valid', function () use ($v, $kini) {
    $h = $v->periksa([anggota(1, 'TAMBAH', '5000000', 1), anggota(2, 'KURANG', '3000000', 2)], $kini);
    expect($h['valid'])->toBeFalse();
});

test('unit yang sama di dua sisi tidak valid', function () use ($v, $kini) {
    $h = $v->periksa([anggota(1, 'TAMBAH', '1000000', 1), anggota(2, 'KURANG', '1000000', 1)], $kini);
    expect($h['valid'])->toBeFalse();
});

test('beda jenis belanja tidak valid', function () use ($v, $kini) {
    $h = $v->periksa([anggota(1, 'TAMBAH', '1000000', 1, 3), anggota(2, 'KURANG', '1000000', 2, 9)], $kini);
    expect($h['valid'])->toBeFalse();
});

test('permintaan yang lewat batas atau tidak terbuka tidak valid', function () use ($v) {
    $h = $v->periksa([anggota(1, 'TAMBAH', '1000000', 1), anggota(2, 'KURANG', '1000000', 2)], new DateTimeImmutable('2026-10-21'));
    expect($h['valid'])->toBeFalse();
    $h = $v->periksa([anggota(1, 'TAMBAH', '1000000', 1, 3, false, 'BERPASANGAN'), anggota(2, 'KURANG', '1000000', 2)], new DateTimeImmutable('2026-10-14'));
    expect($h['valid'])->toBeFalse();
});

test('UAT-45 harga di atas standar tidak boleh disetujui otomatis', function () {
    expect(ValidatorPasangan::bolehOtomatis(true, '5.1.02', true, null))->toBeFalse();
    expect(ValidatorPasangan::bolehOtomatis(false, '5.1.02', true, null))->toBeTrue();
});

test('pembatasan jenis untuk persetujuan otomatis (K-25)', function () {
    expect(ValidatorPasangan::bolehOtomatis(false, '5.2.02', true, ['5.1.02']))->toBeFalse();
    expect(ValidatorPasangan::bolehOtomatis(false, '5.1.02', false, null))->toBeFalse();
});
