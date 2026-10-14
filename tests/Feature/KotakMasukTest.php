<?php

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->user = fn (string $k) => User::query()->where('email', $k.'@demo.test')->firstOrFail();
});

test('login dengan NIP dan halaman utama dapat dibuka setiap peran', function () {
    $this->withoutVite();
    $this->post('/login', ['login' => DemoSeeder::PENGGUNA['farmasi'][4], 'password' => DemoSeeder::SANDI])->assertRedirect('/beranda');
    foreach (['/beranda', '/persetujuan', '/pergeseran', '/standar-harga', '/rba'] as $url) {
        $this->get($url)->assertOk();
    }
});

test('akun nonaktif tidak dapat masuk', function () {
    DB::table('users')->where('email', 'lab@demo.test')->update(['aktif' => false]);
    $this->post('/login', ['login' => 'lab@demo.test', 'password' => DemoSeeder::SANDI])->assertSessionHasErrors('login');
    $this->assertGuest();
});

test('kotak masuk menampilkan pengajuan sesuai kewenangan', function () {
    $tipe = fn (string $k) => collect(app(\App\Modules\Workflow\Services\KotakMasukService::class)->untuk(($this->user)($k)))->pluck('tipe')->sort()->values()->all();

    expect($tipe('kafarmasi'))->toBe(['PASANGAN_UNIT']);
    expect($tipe('kalab'))->toBe(['PASANGAN_UNIT']);
    expect($tipe('perencana'))->toBe(['REVISI', 'USULAN_STANDAR']);
    expect($tipe('pimpinan'))->toBe(['RBA']);
    expect($tipe('farmasi'))->toBe([]);   // pengusul tidak memutuskan pengajuannya sendiri
});

test('pimpinan menetapkan RBA dari kotak masuk dengan PIN dan pagu tahun berikutnya terbentuk', function () {
    $this->actingAs(($this->user)('pimpinan'));
    $rba = DB::table('rba')->where('tahun', 2027)->value('id');

    $this->postJson("/persetujuan/RBA/{$rba}", ['keputusan' => 'SETUJUI', 'pin' => '000000', 'kanal' => 'MOBILE'])
        ->assertStatus(422)->assertJson(['kode' => 'PIN_SALAH']);
    $this->postJson("/persetujuan/RBA/{$rba}", ['keputusan' => 'SETUJUI', 'pin' => DemoSeeder::PIN_PIMPINAN, 'kanal' => 'MOBILE'])
        ->assertOk()->assertJson(['status' => 'DEFINITIF']);

    expect(DB::table('pagu_jenis')->where('tahun', 2027)->count())->toBeGreaterThan(0);
    expect(DB::table('persetujuan_aksi')->where('kanal', 'MOBILE')->where('metode_konfirmasi', 'PIN')->where('aksi', 'SETUJUI')->exists())->toBeTrue();
    expect(DB::select('SELECT * FROM fn_pagu_rekonsiliasi(2027::smallint)'))->toBe([]);
});

test('pergeseran demo dapat diselesaikan dari kotak masuk: dua kepala unit lalu perencana', function () {
    $pasangan = DB::table('pasangan_pergeseran')->first();
    foreach (['kafarmasi', 'kalab'] as $k) {
        $item = collect(app(\App\Modules\Workflow\Services\KotakMasukService::class)->untuk(($this->user)($k)))->firstWhere('tipe', 'PASANGAN_UNIT');
        $this->actingAs(($this->user)($k))
            ->postJson("/persetujuan/PASANGAN_UNIT/{$pasangan->id}", ['keputusan' => 'SETUJUI', 'permintaan_id' => $item['permintaan_id']])->assertOk();
    }
    $this->actingAs(($this->user)('perencana'))
        ->postJson("/persetujuan/PASANGAN/{$pasangan->id}", ['keputusan' => 'SETUJUI', 'catatan' => 'ok'])
        ->assertOk()->assertJson(['status' => 'DISAHKAN']);
    expect(DB::select('SELECT * FROM fn_pagu_rekonsiliasi(2026::smallint)'))->toBe([]);
});

test('penolakan dari kotak masuk wajib beralasan minimal 10 karakter', function () {
    $paket = DB::table('revisi_paket')->where('status', 'MENUNGGU_PERENCANA')->value('id');
    $this->actingAs(($this->user)('perencana'))
        ->postJson("/persetujuan/REVISI/{$paket}", ['keputusan' => 'TOLAK', 'catatan' => 'tidak'])->assertStatus(422);
    $this->postJson("/persetujuan/REVISI/{$paket}", ['keputusan' => 'TOLAK', 'catatan' => 'Belum ada kajian kebutuhan ruang'])
        ->assertOk()->assertJson(['status' => 'DITOLAK']);
});
