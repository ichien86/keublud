<?php

use App\Modules\Shared\Domain\Uang;
use App\Modules\StandarHarga\Domain\NormalisasiUraian;
use App\Modules\StandarHarga\Domain\SkorKecocokan;

test('normalisasi satuan memakai sinonim', function () {
    expect(NormalisasiUraian::satuan('Pcs'))->toBe('buah');
    expect(NormalisasiUraian::satuan('btl.'))->toBe('botol');
    expect(NormalisasiUraian::satuan('rim'))->toBe('rim');
});

test('normalisasi uraian menyatukan angka dan satuan serta membuang kata umum', function () {
    expect(NormalisasiUraian::uraian('Kertas HVS ukuran A4 70 gr'))->toBe('kertas hvs a4 70gram');
    expect(NormalisasiUraian::uraian('Kertas A4', '70gsm'))->toBe('kertas a4 70gram');
});

test('UAT-03 usulan mirip dengan item yang ada berkategori tinggi', function () {
    $s = new SkorKecocokan;
    $h = $s->hitung(0.80, null, '1.1.7.01', 'rim', 'Rim', Uang::dari('52000'), Uang::dari('50000'));
    expect($h['kategori'])->toBe(SkorKecocokan::TINGGI);
    expect($h['skor'])->toBeGreaterThan(0.69);
});

test('tanpa kode barang, kemiripan uraian 0.72 masih berkategori sedang', function () {
    $s = new SkorKecocokan;
    $h = $s->hitung(0.72, null, '1.1.7.01', 'rim', 'Rim', Uang::dari('52000'), Uang::dari('50000'));
    expect($h['kategori'])->toBe(SkorKecocokan::SEDANG);
});

test('item berbeda jauh berkategori tanpa', function () {
    $s = new SkorKecocokan;
    $h = $s->hitung(0.10, null, null, 'unit', 'rim', Uang::dari('31850000'), Uang::dari('50000'));
    expect($h['kategori'])->toBe(SkorKecocokan::TANPA);
});

test('kategori tertinggi dari sekumpulan hasil', function () {
    expect(SkorKecocokan::kategoriTertinggi(['RENDAH', 'TINGGI', 'SEDANG']))->toBe('TINGGI');
    expect(SkorKecocokan::kategoriTertinggi([]))->toBe('TANPA');
});
