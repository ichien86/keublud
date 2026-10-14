<?php

use App\Models\User;
use App\Modules\ImporRba\Services\ImporRbaService;
use App\Modules\Shared\Errors\KesalahanDomain;
use App\Modules\StandarHarga\Models\UsulanStandarBatch;
use App\Modules\StandarHarga\Models\UsulanStandarHarga;
use App\Modules\StandarHarga\Services\UsulanStandarService;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(DemoSeeder::class);
    $this->user = fn (string $k) => User::query()->where('email', $k.'@demo.test')->firstOrFail();
    $this->svc = app(ImporRbaService::class);
    $this->file = fn () => new UploadedFile(base_path('tests/fixtures/rba-format-lama-contoh.xlsx'), 'ANGGARAN 2026.xlsx', null, null, true);
});

/** Mengunggah fixture dan membuat staging untuk tahun 2028 (tahun tanpa RBA). */
function stagingContoh($t): object
{
    $admin = ($t->user)('admin');
    $u = $t->svc->unggah(($t->file)(), $admin);
    expect($u['sheet'])->toContain('anggaran 2026 10122025');
    DB::table('standar_harga_item')->where('tahun', 2027)->update(['tahun' => 2028]);   // katalog tahun tujuan
    $uuid = $t->svc->mulai($u['batch_uuid'], 'anggaran 2026 10122025', 0, 2028, (int) DB::table('sumber_dana')->where('kode', 'JL')->value('id'), $admin);

    return DB::table('impor_rba')->where('uuid', $uuid)->first();
}

test('pratinjau membaca sheet pilihan dan rekonsiliasi file', function () {
    $u = $this->svc->unggah(($this->file)(), ($this->user)('admin'));
    $p = $this->svc->pratinjau($u['batch_uuid'], 'JADI 300', 1, ($this->user)('admin'));

    expect($p['kelompok'])->toBe(['Anggaran Belanja Penggeseran', 'Anggaran Belanja Perubahan']);
    expect($p['total'])->toBe('120119674274.80');
    expect(collect($p['rekonsiliasi'])->every(fn ($r) => $r['cocok']))->toBeTrue();
});

test('alur lengkap: staging → kodefikasi → label unit → usulan item → disahkan → RBA draf', function () {
    $impor = stagingContoh($this);
    expect(DB::table('staging_rba')->where('impor_rba_id', $impor->id)->count())->toBe(53);
    expect(DB::table('staging_rba')->where('impor_rba_id', $impor->id)->where('status', 'GAGAL')->count())->toBeGreaterThan(0);   // kodefikasi belum lengkap

    // Kodefikasi dari file (administrator)
    expect(fn () => $this->svc->lengkapiKodefikasi($impor->id, ($this->user)('penyusun')))->toThrow(KesalahanDomain::class);
    $k = $this->svc->lengkapiKodefikasi($impor->id, ($this->user)('admin'));
    expect($k['pemetaan'])->toBeGreaterThan(10);
    expect(DB::table('staging_rba')->where('impor_rba_id', $impor->id)->where('status', 'GAGAL')->count())->toBe(0);

    // Label → unit; sisanya ke satu unit
    $this->svc->petakanLabel($impor->id, 'UMUM', null, 'Bagian Umum', ($this->user)('penyusun'));
    expect(DB::table('pemetaan_label_unit')->where('label', 'UMUM')->exists())->toBeTrue();
    $this->svc->unitMassal($impor->id, (int) DB::table('unit_kerja')->where('kode', 'FAR')->value('id'), ($this->user)('penyusun'));

    // Satu baris dirinci manual ke item standar (Paracetamol 2028 = 105.000), sisanya diusulkan sebagai item
    $obat = DB::table('staging_rba')->where('impor_rba_id', $impor->id)->where('kode_blud', '5.1.02.01.01.0037')->first();
    $para = DB::table('standar_harga_item')->where('tahun', 2028)->where('kode_barang', '1.1.12.01.01')->first();
    expect(fn () => $this->svc->simpanItem($obat->id, [['item_id' => $para->id, 'volume' => '1', 'harga' => '999999']], null, ($this->user)('penyusun')))
        ->toThrow(KesalahanDomain::class, 'melebihi harga standar');
    $this->svc->simpanItem($obat->id, [['item_id' => $para->id, 'volume' => '104761.9047', 'harga' => $para->harga]], null, ($this->user)('penyusun'));
    expect(DB::table('staging_rba')->where('id', $obat->id)->value('status'))->toBe('PERLU_DIRINCI');   // belum tepat sama
    $this->svc->abaikan($obat->id, 'Obat dianggarkan ulang lewat e-katalog', ($this->user)('penyusun'));

    $hasil = $this->svc->ajukanUsulan($impor->id, ($this->user)('penyusun'));
    expect($hasil['diajukan'])->toBe(52);
    $batch = UsulanStandarBatch::query()->findOrFail(DB::table('impor_rba')->where('id', $impor->id)->value('usulan_batch_id'));
    $sah = app(UsulanStandarService::class)->sahkanMemenuhiSyarat($batch, ($this->user)('perencana'));
    expect($sah['disahkan'] + $sah['dilewati'])->toBe(52);
    // usulan yang berkecocokan tinggi diputuskan satu per satu
    foreach (DB::table('usulan_standar_harga')->where('batch_id', $batch->id)->where('status', 'DIAJUKAN')->pluck('id') as $id) {
        app(UsulanStandarService::class)->putuskan(UsulanStandarHarga::query()->find($id), ($this->user)('perencana'),
            UsulanStandarService::SAHKAN, null, 'Paket tahunan hasil impor RBA, bukan item satuan');
    }

    $this->svc->segarkan($impor->id);
    expect(DB::table('staging_rba')->where('impor_rba_id', $impor->id)->whereNotIn('status', ['SIAP', 'DIABAIKAN'])->count())->toBe(0);

    // Rekonsiliasi: diabaikan ikut dihitung, total cocok
    expect(collect($this->svc->rekonsiliasi($impor->id))->every(fn ($r) => $r['cocok']))->toBeTrue();
    $rbaId = $this->svc->bentukRba($impor->id, ($this->user)('penyusun'));

    $rba = DB::table('rba')->find($rbaId);
    expect($rba->status)->toBe('DRAF');
    expect($rba->tahun)->toBe(2028);
    expect((string) DB::table('rba_rincian')->where('rba_id', $rbaId)->sum('nilai'))->toBe('83000000000.00');   // 94 M − 11 M obat diabaikan
    expect(DB::table('rba_rincian')->where('rba_id', $rbaId)->whereNull('unit_id')->exists())->toBeFalse();
    expect(DB::table('rba_rincian')->where('rba_id', $rbaId)->where('label_lama', 'UMUM')->value('unit_id'))
        ->toBe((int) DB::table('unit_kerja')->where('nama', 'Bagian Umum')->value('id'));
});

test('RBA tidak dibentuk bila masih ada baris belum siap', function () {
    $impor = stagingContoh($this);

    expect(fn () => $this->svc->bentukRba($impor->id, ($this->user)('penyusun')))
        ->toThrow(KesalahanDomain::class, 'belum siap');
});

test('halaman impor dapat dibuka dan pengguna tanpa kewenangan ditolak', function () {
    $this->withoutVite();
    $impor = stagingContoh($this);
    $this->actingAs(($this->user)('penyusun'))->get('/impor-rba')->assertOk();
    $this->get('/impor-rba/'.$impor->uuid)->assertOk();
    $this->actingAs(($this->user)('farmasi'))->get('/impor-rba')->assertStatus(403);
});
