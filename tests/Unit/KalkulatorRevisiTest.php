<?php

use App\Modules\Revisi\Domain\BarisRevisi;
use App\Modules\Revisi\Domain\KalkulatorRevisi;
use App\Modules\Revisi\Domain\RincianBerlaku;
use App\Modules\Shared\Domain\Uang;

function rincian(int $id, string $nilai, string $pakai = '0', bool $kunci = false): RincianBerlaku
{
    return new RincianBerlaku($id, Uang::dari($nilai), Uang::dari($pakai), Uang::nol(), 1, $kunci);
}

function ubah(int $id, string $vol, string $harga, ?string $std = null): BarisRevisi
{
    return new BarisRevisi('UBAH', $id, $vol, Uang::dari($harga), Uang::dari($std ?? $harga), 1);
}

function tambah(string $vol, string $harga, ?string $std = null): BarisRevisi
{
    return new BarisRevisi('TAMBAH', null, $vol, Uang::dari($harga), Uang::dari($std ?? $harga));
}

$k = new KalkulatorRevisi;
$pagu = Uang::dari('100000000');
$rincian = [rincian(1, '60000000'), rincian(2, '40000000')];

test('UAT-09 revisi seimbang Belanja Barang dan Jasa langsung berlaku', function () use ($k, $pagu, $rincian) {
    // pindah 5 jt dari rincian 1 ke rincian 2
    $h = $k->hitung($pagu, $rincian, [ubah(1, '550', '100000'), ubah(2, '450', '100000')], true);
    expect($h->status)->toBe(KalkulatorRevisi::SEIMBANG);
    expect($h->keputusan)->toBe(KalkulatorRevisi::KEPUTUSAN_BERLAKU);
});

test('UAT-49 revisi seimbang jenis lain menunggu perencana', function () use ($k, $pagu, $rincian) {
    $h = $k->hitung($pagu, $rincian, [ubah(1, '550', '100000'), ubah(2, '450', '100000')], false);
    expect($h->keputusan)->toBe(KalkulatorRevisi::KEPUTUSAN_MENUNGGU_PERENCANA);
    expect($h->alasanMenunggu)->toBe('JENIS_PERLU_PERSETUJUAN');
});

test('UAT-11 harga di atas standar menunggu perencana', function () use ($k, $pagu, $rincian) {
    $h = $k->hitung($pagu, $rincian, [ubah(1, '500', '120000', '100000')], true);
    expect($h->status)->toBe(KalkulatorRevisi::SEIMBANG);
    expect($h->memuatDiAtasStandar)->toBeTrue();
    expect($h->alasanMenunggu)->toBe('DI_ATAS_STANDAR');
});

test('UAT-31 indikator menunjukkan pagu bertambah', function () use ($k, $pagu, $rincian) {
    $h = $k->hitung($pagu, $rincian, [ubah(1, '650', '100000')], true);
    expect($h->status)->toBe(KalkulatorRevisi::BERTAMBAH);
    expect($h->selisih->keDesimal())->toBe('5000000.00');
    expect($h->keputusan)->toBe(KalkulatorRevisi::KEPUTUSAN_PERLU_PERGESERAN);
    expect($h->arahPergeseran())->toBe('TAMBAH');
});

test('pagu berkurang menghasilkan permintaan kurang', function () use ($k, $pagu, $rincian) {
    $h = $k->hitung($pagu, $rincian, [ubah(2, '300', '100000')], true);
    expect($h->status)->toBe(KalkulatorRevisi::BERKURANG);
    expect($h->arahPergeseran())->toBe('KURANG');
    expect($h->selisih->abs()->keDesimal())->toBe('10000000.00');
});

test('rincian baru dari daftar standar dihitung dalam total', function () use ($k, $pagu, $rincian) {
    $h = $k->hitung($pagu, $rincian, [ubah(1, '550', '100000'), tambah('100', '50000')], true);
    expect($h->status)->toBe(KalkulatorRevisi::SEIMBANG);
});

test('UAT-13 nilai baru di bawah realisasi + komitmen ditolak', function () use ($k) {
    $h = $k->hitung(Uang::dari('10000000'), [rincian(1, '10000000', '8000000')], [ubah(1, '70', '100000'), tambah('30', '100000')], true);
    expect($h->keputusan)->toBe(KalkulatorRevisi::KEPUTUSAN_DITOLAK);
    expect($h->pelanggaran[0]->kode)->toBe('DI_BAWAH_PEMAKAIAN');
});

test('UAT-33 rincian yang terkunci paket lain tidak dapat diubah', function () use ($k, $pagu) {
    $h = $k->hitung($pagu, [rincian(1, '60000000', '0', true), rincian(2, '40000000')], [ubah(1, '600', '100000')], true);
    expect($h->keputusan)->toBe(KalkulatorRevisi::KEPUTUSAN_DITOLAK);
    expect($h->pelanggaran[0]->kode)->toBe('RINCIAN_TERKUNCI');
});

test('menghapus rincian yang sudah dipakai ditolak', function () use ($k) {
    $h = $k->hitung(Uang::dari('10000000'), [rincian(1, '10000000', '1')],
        [new BarisRevisi('HAPUS', 1, '0', Uang::nol(), Uang::nol(), 1)], true);
    expect($h->pelanggaran[0]->kode)->toBe('DI_BAWAH_PEMAKAIAN');
});

test('konflik versi terdeteksi', function () use ($k, $pagu, $rincian) {
    $baris = new BarisRevisi('UBAH', 1, '600', Uang::dari('100000'), Uang::dari('100000'), 7);
    $h = $k->hitung($pagu, $rincian, [$baris], true);
    expect($h->pelanggaran[0]->kode)->toBe('KONFLIK_VERSI');
});
