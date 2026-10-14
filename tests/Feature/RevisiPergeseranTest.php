<?php

use App\Modules\Anggaran\Models\PaguJenis;
use App\Modules\Pergeseran\Jobs\ProsesBatasPasangan;
use App\Modules\Pergeseran\Jobs\ProsesBatasPengesahan;
use App\Modules\Pergeseran\Models\PasanganPergeseran;
use App\Modules\Pergeseran\Models\PermintaanPergeseran;
use App\Modules\Pergeseran\Services\PasanganService;
use App\Modules\Revisi\Models\RevisiPaket;
use App\Modules\Revisi\Services\RevisiService;
use App\Modules\Shared\Errors\KesalahanDomain;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Carbon::setTestNow('2026-10-12 09:00:00');   // Senin
    $this->u = $this->muatFixture();
    $this->revisi = app(RevisiService::class);
    $this->pasangan = app(PasanganService::class);
    // Identity tidak di-reset antar-test: id rincian fixture dipetakan dari uraian awalnya.
    $this->rin = DB::table('rba_rincian')->pluck('id', 'uraian')->map(fn ($id) => (int) $id)->all();
    $this->rek = fn (string $bagan, string $kode) => (int) DB::selectOne('SELECT rek(?, ?) AS id', [$bagan, $kode])->id;
});

function idItem(string $kode): int
{
    return (int) DB::table('standar_harga_item')->where('kode_barang', $kode)->value('id');
}

function barisUbah(int $rincianId, string $volume, string $harga, int $itemId, int $rekeningBludId): array
{
    return ['aksi' => 'UBAH', 'rincian_id' => $rincianId, 'item_id' => $itemId, 'rekening_blud_id' => $rekeningBludId, 'volume' => $volume, 'harga' => $harga];
}

test('UAT-09 revisi seimbang Belanja Barang dan Jasa langsung berlaku', function () {
    $pagu = PaguJenis::query()->findOrFail($this->paguId('FAR', '5.1.02'));
    $paket = $this->revisi->bukaDraf($pagu, $this->u['far']);
    $obat = ($this->rek)('BLUD', '5.1.02.01.01.0037');
    $atk = ($this->rek)('BLUD', '5.1.02.01.01.0024');

    // Paracetamol 500 -> 450 box (-5 jt), Kertas 200 -> 300 rim (+5 jt)
    $h = $this->revisi->simpanBaris($paket, $this->u['far'], [barisUbah($this->rin['Paracetamol'], '450', '100000', idItem('1.1.12.01'), $obat), barisUbah($this->rin['Kertas'], '300', '50000', idItem('1.1.7.01'), $atk)]);
    expect($h->status)->toBe('SEIMBANG');

    $paket = $this->revisi->simpan($paket->refresh(), $this->u['far']);
    expect($paket->status)->toBe('BERLAKU');
    expect(DB::table('rba_rincian')->where('id', $this->rin['Kertas'])->value('volume'))->toEqual('300.0000');
    expect(DB::select('SELECT * FROM fn_pagu_rekonsiliasi(2026::smallint)'))->toBe([]);
});

test('UAT-32/37 kenaikan pagu dikirim sebagai pergeseran, dipasangkan, lalu disahkan', function () {
    $atk = ($this->rek)('BLUD', '5.1.02.01.01.0024');

    // Farmasi menaikkan Kertas +3 jt -> PERLU_PERSETUJUAN
    $paketFar = $this->revisi->bukaDraf(PaguJenis::query()->findOrFail($this->paguId('FAR', '5.1.02')), $this->u['far']);
    $this->revisi->simpanBaris($paketFar, $this->u['far'], [barisUbah($this->rin['Kertas'], '260', '50000', idItem('1.1.7.01'), $atk)]);
    expect(fn () => $this->revisi->simpan($paketFar->refresh(), $this->u['far']))->toThrow(KesalahanDomain::class);
    $paketFar = $this->revisi->kirimPergeseran($paketFar->refresh(), $this->u['far']);
    expect($paketFar->status)->toBe('MENUNGGU_PERGESERAN');
    expect(DB::table('rba_rincian')->where('id', $this->rin['Kertas'])->value('terkunci_paket_id'))->toBe($paketFar->id);

    // Lab mengurangi Kertas Lab -3 jt -> permintaan KURANG, dicadangkan
    $paguLab = PaguJenis::query()->findOrFail($this->paguId('LAB', '5.1.02'));
    $paketLab = $this->revisi->bukaDraf($paguLab, $this->u['lab']);
    $this->revisi->simpanBaris($paketLab, $this->u['lab'], [barisUbah($this->rin['Kertas Lab'], '540', '50000', idItem('1.1.7.01'), $atk)]);
    $paketLab = $this->revisi->kirimPergeseran($paketLab->refresh(), $this->u['lab']);
    expect((string) $paguLab->refresh()->tercadang)->toBe('3000000.00');

    // Perencana memasangkan; kedua unit harus menyetujui
    $pasangan = $this->pasangan->bentuk([$paketFar->permintaan_id, $paketLab->permintaan_id], $this->u['perencana']);
    expect($pasangan->status)->toBe('MENUNGGU_UNIT');
    $pasangan = $this->pasangan->putuskanUnit($pasangan, $paketFar->permintaan_id, $this->u['kafar'], true);
    $pasangan = $this->pasangan->putuskanUnit($pasangan, $paketLab->permintaan_id, $this->u['kalab'], true);
    expect($pasangan->status)->toBe('MENUNGGU_PERENCANA');
    expect($pasangan->batas_pengesahan->format('Y-m-d'))->toBe('2026-10-19');

    $pasangan = $this->pasangan->sahkan($pasangan, $this->u['perencana'], 'Sesuai kebutuhan');
    expect($pasangan->status)->toBe('DISAHKAN');
    expect((string) PaguJenis::query()->findOrFail($this->paguId('FAR', '5.1.02'))->pagu_berlaku)->toBe('63000000.00');
    expect((string) $paguLab->refresh()->pagu_berlaku)->toBe('27000000.00');
    expect((string) $paguLab->tercadang)->toBe('0.00');
    expect(DB::select('SELECT * FROM fn_pagu_rekonsiliasi(2026::smallint)'))->toBe([]);
});

test('UAT-40/41 permintaan tanpa pasangan ditolak otomatis setelah 5 hari kerja dan pencadangan dilepas', function () {
    $atk = ($this->rek)('BLUD', '5.1.02.01.01.0024');
    $paguLab = PaguJenis::query()->findOrFail($this->paguId('LAB', '5.1.02'));
    $paket = $this->revisi->bukaDraf($paguLab, $this->u['lab']);
    $this->revisi->simpanBaris($paket, $this->u['lab'], [barisUbah($this->rin['Kertas Lab'], '540', '50000', idItem('1.1.7.01'), $atk)]);
    $paket = $this->revisi->kirimPergeseran($paket->refresh(), $this->u['lab']);

    Carbon::setTestNow('2026-10-20 00:01:00');   // lewat akhir hari kerja ke-5 (Senin 19 Okt 23:59:59)
    (new ProsesBatasPasangan)->handle(app(\App\Modules\Pergeseran\Services\PermintaanService::class), $this->pasangan);

    expect(PermintaanPergeseran::query()->findOrFail($paket->permintaan_id)->status)->toBe('DITOLAK_OTOMATIS');
    expect(RevisiPaket::query()->findOrFail($paket->id)->status)->toBe('GUGUR');
    expect((string) $paguLab->refresh()->tercadang)->toBe('0.00');
    expect(DB::table('rba_rincian')->where('id', $this->rin['Kertas Lab'])->value('terkunci_paket_id'))->toBeNull();
});

test('UAT-43 pasangan disetujui otomatis setelah 5 hari kerja tanpa respons perencana', function () {
    $atk = ($this->rek)('BLUD', '5.1.02.01.01.0024');
    $paketFar = $this->revisi->bukaDraf(PaguJenis::query()->findOrFail($this->paguId('FAR', '5.1.02')), $this->u['far']);
    $this->revisi->simpanBaris($paketFar, $this->u['far'], [barisUbah($this->rin['Kertas'], '260', '50000', idItem('1.1.7.01'), $atk)]);
    $paketFar = $this->revisi->kirimPergeseran($paketFar->refresh(), $this->u['far']);
    $paketLab = $this->revisi->bukaDraf(PaguJenis::query()->findOrFail($this->paguId('LAB', '5.1.02')), $this->u['lab']);
    $this->revisi->simpanBaris($paketLab, $this->u['lab'], [barisUbah($this->rin['Kertas Lab'], '540', '50000', idItem('1.1.7.01'), $atk)]);
    $paketLab = $this->revisi->kirimPergeseran($paketLab->refresh(), $this->u['lab']);

    $p = $this->pasangan->bentuk([$paketFar->permintaan_id, $paketLab->permintaan_id], $this->u['lab']);
    $p = $this->pasangan->putuskanUnit($p, $paketFar->permintaan_id, $this->u['kafar'], true);
    expect($p->status)->toBe('MENUNGGU_PERENCANA');

    Carbon::setTestNow('2026-10-20 00:05:00');
    (new ProsesBatasPengesahan)->handle($this->pasangan, app(\App\Modules\Shared\Notifikasi\Penerima::class));

    $p = PasanganPergeseran::query()->findOrFail($p->id);
    expect($p->status)->toBe('DISETUJUI_OTOMATIS');
    expect(DB::table('persetujuan_aksi')->where('aksi', 'SETUJUI_OTOMATIS')->whereNull('pelaku_id')->exists())->toBeTrue();
});

test('baris yang tidak berubah diabaikan dan simpan tanpa perubahan ditolak', function () {
    $paket = $this->revisi->bukaDraf(PaguJenis::query()->findOrFail($this->paguId('FAR', '5.1.02')), $this->u['far']);
    $h = $this->revisi->simpanBaris($paket, $this->u['far'], [barisUbah($this->rin['Kertas'], '200.0000', '50000.00', idItem('1.1.7.01'), ($this->rek)('BLUD', '5.1.02.01.01.0024'))]);
    expect(DB::table('revisi_detail')->where('paket_id', $paket->id)->count())->toBe(0);
    expect(fn () => $this->revisi->simpan($paket->refresh(), $this->u['far']))->toThrow(KesalahanDomain::class, 'Belum ada rincian yang diubah');
});

test('UAT-27 pengusul tidak dapat menyetujui revisinya sendiri', function () {
    $this->u['perencana']->assignRole('pengusul_unit');
    DB::table('user_unit')->insert(['user_id' => $this->u['perencana']->id, 'unit_id' => DB::table('unit_kerja')->where('kode', 'LAB')->value('id')]);
    $paguModal = PaguJenis::query()->findOrFail($this->paguId('LAB', '5.2.02'));
    $alkes = ($this->rek)('BLUD', '5.2.02.07.01.0001');

    $paket = $this->revisi->bukaDraf($paguModal, $this->u['perencana']);
    // Pecah 2 bed menjadi dua rincian (seimbang) — Belanja Modal tetap wajib persetujuan perencana.
    $this->revisi->simpanBaris($paket, $this->u['perencana'], [
        barisUbah($this->rin['Bed'], '1', '31850000', idItem('3.5.1.01'), $alkes),
        ['aksi' => 'TAMBAH', 'item_id' => idItem('3.5.1.01'), 'rekening_blud_id' => $alkes, 'volume' => '1', 'harga' => '31850000', 'uraian' => 'Bed ruang isolasi'],
    ]);
    $paket = $this->revisi->simpan($paket->refresh(), $this->u['perencana']);
    expect($paket->status)->toBe('MENUNGGU_PERENCANA');   // UAT-49: Belanja Modal perlu persetujuan

    expect(fn () => $this->revisi->setujui($paket, $this->u['perencana']))->toThrow(KesalahanDomain::class);
});
