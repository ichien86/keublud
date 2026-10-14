<?php

// @butuh-vendor (PhpSpreadsheet)

use App\Modules\ImporRba\Domain\LampiranRbaParser;
use App\Modules\ImporRba\Services\PembacaXlsx;
use App\Modules\Shared\Domain\Uang;

// Fixture: struktur asli lampiran RBA BLUD (nama dianonimkan, kolom catatan dibuang).
function bacaContoh(string $sheet, int $kelompok = 0)
{
    $f = __DIR__.'/../fixtures/rba-format-lama-contoh.xlsx';

    return (new LampiranRbaParser)->baca((new PembacaXlsx)->baca($f, $sheet), $kelompok);
}

function baris(\App\Modules\ImporRba\Domain\HasilBacaRba $h, int $no): \App\Modules\ImporRba\Domain\BarisRba
{
    return collect($h->baris)->first(fn ($b) => $b->baris === $no) ?? throw new RuntimeException("baris {$no} tidak ada");
}

test('kepala dokumen, kelompok nilai, dan rekonsiliasi per rekening Pemda cocok dengan file', function () {
    $h = bacaContoh('anggaran 2026 10122025');

    expect($h->galat)->toBeNull();
    expect($h->kelompok)->toBe(['Anggaran Belanja Penetapan']);
    expect($h->kepala['Sub Kegiatan']['kode'])->toBe('1.02.01.2.10.01');
    expect($h->kepala['Tahun Anggaran']['kode'])->toBe('2026');
    foreach ($h->rekonsiliasi() as $r) {
        expect($r['cocok'])->toBeTrue("{$r['kode']} file {$r['file']->format()} terbaca {$r['terbaca']->format()}");
    }
    expect(array_reduce($h->baris, fn ($t, $b) => $t->tambah($b->jumlah), Uang::nol())->sama(Uang::dari(94_000_000_000)))->toBeTrue();
});

test('kode rinci BLUD disusun dari segmen di bawah jenis Pemda', function () {
    $obat = baris(bacaContoh('anggaran 2026 10122025'), 78);

    expect($obat->kodeBlud)->toBe('5.1.02.01.01.0037');
    expect($obat->kodePemda)->toBe('5.1.02.99.99.9999');
    expect($obat->komponen)->toBe('Belanja Obat-Obatan-Obat');
    expect($obat->jumlah->sama(Uang::dari(11_000_000_000)))->toBeTrue();
    expect($obat->gelondongan)->toBeTrue();   // 1 tahun → wajib dirinci ke item standar harga
});

test('sub-komponen bernilai yang jumlahnya cocok menggantikan komponen; label unit dibaca', function () {
    $h = bacaContoh('anggaran 2026 10122025');
    $laso = baris($h, 217);

    expect($laso->kodeBlud)->toBe('5.2.02.07.01.0004');
    expect($laso->rincian)->toBe('Lasotronik');
    expect($laso->volume)->toBe('1');
    expect($laso->satuan)->toBe('unit');
    expect($laso->label)->toBe('IBS-Bedah Saraf');
    expect($laso->gelondongan)->toBeFalse();
    expect(baris($h, 218)->jumlah->sama(Uang::dari(880_000_000)))->toBeTrue();
});

test('kelompok A./B dan rincian di bawahnya membentuk jalur uraian', function () {
    $b = baris(bacaContoh('anggaran 2026 10122025'), 272);

    expect($b->rincian)->toBe('B. Pembangunan Gedung IGD › Konsultan Pengawas');
    expect($b->jumlah->sama(Uang::dari(120_000_000)))->toBeTrue();
});

test('label penanggung jawab diwarisi dari judul rincian objek', function () {
    expect(baris(bacaContoh('anggaran 2026 10122025'), 168)->label)->toBe('Penunjang');
});

test('nilai di baris judul rincian objek dibebankan ke komponen pertama di bawahnya', function () {
    $sewa = baris(bacaContoh('anggaran 2026 10122025'), 149);

    expect($sewa->kodeBlud)->toBe('5.1.02.02.05.0002');
    expect($sewa->jumlah->sama(Uang::dari(20_000_000)))->toBeTrue();
    expect($sewa->peringatan[0])->toContain('baris judul');
});

test('sheet pembanding: kelompok kedua (Perubahan) dapat dipilih dan rumus rusak dinolkan', function () {
    $h = bacaContoh('JADI 300', 1);

    expect($h->kelompok)->toBe(['Anggaran Belanja Penggeseran', 'Anggaran Belanja Perubahan']);
    expect($h->kelompokDipakai)->toBe('Anggaran Belanja Perubahan');
    expect(collect($h->rekonsiliasi())->every(fn ($r) => $r['cocok']))->toBeTrue();
    expect(collect($h->peringatan)->contains(fn ($p) => str_contains($p, 'Jml bernilai 0')))->toBeTrue();
});

test('sheet tanpa judul tabel ditolak dengan pesan jelas', function () {
    $h = (new LampiranRbaParser)->baca([['Rekap'], ['A', 'B']]);

    expect($h->galat)->toContain('Baris judul tabel');
});

test('angka teks gaya Indonesia dan Inggris dibaca tanpa float', function () {
    $baris = [
        ['KODE REKENING', null, null, null, null, 'KOMPONEN', null, null, null, 'Anggaran Belanja Penetapan'],
        [null, null, null, null, null, null, null, null, null, 'Jml', 'Satuan', 'Harga Satuan', 'Jumlah'],
        [null, '5.1.02.99.99.9999', null, null, null, 'BBJ', null, null, null, null, null, null, '1.250.000,50'],
        [null, null, '01.', '01.', '0024', null, null, 'ATK', 'Umum', '2', 'rim', '625,000.25', '1.250.000,50'],
    ];
    $h = (new LampiranRbaParser)->baca($baris);

    expect($h->galat)->toBeNull();
    expect($h->baris[0]->kodeBlud)->toBe('5.1.02.01.01.0024');
    expect($h->baris[0]->jumlah->keDesimal())->toBe('1250000.50');
    expect($h->baris[0]->harga->keDesimal())->toBe('625000.25');
    expect($h->baris[0]->label)->toBe('Umum');
});
