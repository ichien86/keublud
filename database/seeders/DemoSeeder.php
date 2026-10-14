<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Anggaran\Models\PaguJenis;
use App\Modules\Anggaran\Models\Rba;
use App\Modules\Anggaran\Services\PenetapanRbaService;
use App\Modules\Anggaran\Services\PersetujuanRbaService;
use App\Modules\Pergeseran\Services\PasanganService;
use App\Modules\Revisi\Services\RevisiService;
use App\Modules\StandarHarga\Services\UsulanStandarService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Data contoh untuk ujicoba lokal: `php artisan migrate:fresh --seed --seeder=DemoSeeder`.
 * BUKAN data riil RSUD. Seluruh pengajuan dibuat melalui service yang sama dengan aplikasi,
 * sehingga aturan pagu, standar harga, dan alur persetujuan ikut teruji.
 */
class DemoSeeder extends Seeder
{
    public const SANDI = 'demo12345';

    public const PIN_PIMPINAN = '123456';

    /** kunci => [nama, jabatan, peran, unit|null, nip] */
    public const PENGGUNA = [
        'admin' => ['Administrator Sistem', 'Pengelola Aplikasi', 'administrator', null, '199001012020011001'],
        'penyusun' => ['Rina Staf Perencanaan', 'Staf Perencanaan', 'perencana', null, '199202022020012002'],
        'perencana' => ['Budi Kasubbag Perencanaan', 'Kasubbag Perencanaan', 'perencana', null, '198503032010011003'],
        'verifikator' => ['Sari Verifikator', 'Verifikator Anggaran', 'verifikator', null, '198804042012012004'],
        'keuangan' => ['Dewi Pejabat Keuangan', 'Pejabat Keuangan', 'pejabat_keuangan', null, '197905052005012005'],
        'pimpinan' => ['dr. Andi Direktur', 'Direktur / Pimpinan BLUD', 'pimpinan_blud', null, '197506062000031006'],
        'farmasi' => ['Fajar Pengusul Farmasi', 'Staf Instalasi Farmasi', 'pengusul_unit', 'FAR', '199407072019031007'],
        'kafarmasi' => ['apt. Maya Kepala Farmasi', 'Kepala Instalasi Farmasi', 'kepala_unit', 'FAR', '198108082008012008'],
        'lab' => ['Lukman Pengusul Lab', 'Staf Instalasi Laboratorium', 'pengusul_unit', 'LAB', '199509092020121009'],
        'kalab' => ['dr. Nina Kepala Lab', 'Kepala Instalasi Laboratorium', 'kepala_unit', 'LAB', '198010102006042010'],
        'radiologi' => ['Rudi Pengusul Radiologi', 'Staf Instalasi Radiologi', 'pengusul_unit', 'RAD', '199611112021011011'],
        'karadiologi' => ['dr. Tono Kepala Radiologi', 'Kepala Instalasi Radiologi', 'kepala_unit', 'RAD', '197912122005011012'],
        'igd' => ['Gita Pengusul IGD', 'Staf IGD', 'pengusul_unit', 'IGD', '199701132022032013'],
        'kaigd' => ['dr. Hadi Kepala IGD', 'Kepala IGD', 'kepala_unit', 'IGD', '198202142009011014'],
    ];

    /** [unit, rekening BLUD, kode barang, uraian, volume] */
    private const RINCIAN = [
        ['FAR', '5.1.02.01.01.0037', '1.1.12.01.01', 'Paracetamol 500 mg', 500],
        ['FAR', '5.1.02.01.01.0037', '1.1.12.01.02', 'Amoxicillin 500 mg', 200],
        ['FAR', '5.1.02.01.01.0037', '1.1.12.01.03', 'Ceftriaxone 1 g', 2000],
        ['FAR', '5.1.02.01.01.0024', '1.1.7.01.01', 'Kertas HVS A4 Farmasi', 200],
        ['LAB', '5.1.02.01.01.0013', '1.1.12.03.01', 'Reagen hematologi', 12],
        ['LAB', '5.1.02.01.01.0012', '1.1.12.03.02', 'Tabung vakum EDTA', 150],
        ['LAB', '5.1.02.01.01.0024', '1.1.7.01.01', 'Kertas HVS A4 Lab', 600],
        ['LAB', '5.2.02.07.01.0001', '3.5.1.02.01', 'Centrifuge laboratorium', 2],
        ['RAD', '5.1.02.01.01.0012', '1.1.12.04.01', 'Film rontgen digital', 20],
        ['RAD', '5.1.02.01.01.0024', '1.1.7.02.01', 'Tinta printer Radiologi', 40],
        ['IGD', '5.1.02.01.01.0012', '1.1.12.02.01', 'Spuit 3 ml', 120],
        ['IGD', '5.1.02.01.01.0012', '1.1.12.02.02', 'Sarung tangan pemeriksaan', 300],
        ['IGD', '5.2.02.07.01.0001', '3.5.1.01.01', 'Bed pasien ruang resusitasi', 2],
        ['IGD', '5.2.02.07.01.0001', '3.5.1.01.01', 'Bed pasien ruang observasi', 1],
        ['IGD', '5.2.02.07.01.0001', '3.5.1.01.02', 'Monitor pasien IGD', 2],
    ];

    /** @var array<string, User> */
    private array $u = [];

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        DB::unprepared((string) file_get_contents(database_path('seeders/sql/demo_master.sql')));
        $this->pengguna();

        // RBA 2026 definitif → pagu terbentuk lewat ledger.
        $rba2026 = $this->rba(2026, 'MENUNGGU_PENETAPAN');
        app(PenetapanRbaService::class)->tetapkanDefinitif($rba2026, '900/012/RSUD/2026', new \DateTimeImmutable('2026-01-02'));

        // RBA 2027 diajukan, diverifikasi, disetujui internal → menunggu penetapan Pimpinan BLUD.
        $rba2027 = $this->rba(2027, 'DRAF', faktor: 1.1);
        $svcRba = app(PersetujuanRbaService::class);
        $rba2027 = $svcRba->ajukan($rba2027, $this->u['penyusun']);
        $rba2027 = $svcRba->setujui($rba2027, $this->u['verifikator'], 'Rincian sesuai standar harga 2027');
        $svcRba->setujui($rba2027, $this->u['perencana'], 'Disetujui untuk ditetapkan');

        $this->pengajuanContoh();
    }

    private function pengguna(): void
    {
        foreach (self::PENGGUNA as $kunci => [$nama, $jabatan, $peran, $unit, $nip]) {
            $user = User::query()->forceCreate([
                'name' => $nama, 'jabatan' => $jabatan, 'nip' => $nip, 'email' => $kunci.'@demo.test',
                'password' => self::SANDI, 'email_verified_at' => now(),
                'pin_hash' => $kunci === 'pimpinan' ? Hash::make(self::PIN_PIMPINAN) : null,
                'mode_sederhana' => $kunci === 'pimpinan',
            ]);
            $user->assignRole($peran);
            if ($unit !== null) {
                DB::table('user_unit')->insert(['user_id' => $user->id, 'unit_id' => $this->unit($unit)]);
            }
            $this->u[$kunci] = $user;
        }
    }

    private function rba(int $tahun, string $status, float $faktor = 1.0): Rba
    {
        $rba = Rba::query()->forceCreate([
            'tahun' => $tahun, 'versi' => 1, 'jenis_versi' => 'AWAL', 'status' => 'DRAF', 'dibuat_oleh' => $this->u['penyusun']->id,
        ]);
        foreach (self::RINCIAN as [$unit, $rekBlud, $kode, $uraian, $volume]) {
            $item = DB::table('standar_harga_item')->where('tahun', $tahun)->where('kode_barang', $kode)->first();
            $pemda = (int) DB::table('pemetaan_rekening')->where('rekening_blud_id', $this->rek('BLUD', $rekBlud))->value('rekening_pemda_id');
            DB::table('rba_rincian')->insert([
                'rba_id' => $rba->id, 'unit_id' => $this->unit($unit), 'sumber_dana_id' => $this->sumberDana('JL'),
                'rekening_blud_id' => $this->rek('BLUD', $rekBlud), 'rekening_pemda_id' => $pemda,
                'jenis_id' => (int) DB::table('rekening')->where('id', $pemda)->value('jenis_id'),
                'item_id' => $item->id, 'uraian' => $uraian, 'volume' => (int) ceil($volume * $faktor), 'satuan' => $item->satuan,
                'harga_standar' => $item->harga, 'harga' => $item->harga,
            ]);
        }
        $rba->forceFill(['status' => $status])->save();

        return $rba->refresh();
    }

    /** Pengajuan yang menunggu keputusan, supaya setiap peran punya sesuatu untuk dicoba. */
    private function pengajuanContoh(): void
    {
        $revisi = app(RevisiService::class);
        $pasangan = app(PasanganService::class);

        // 1) Pergeseran antarunit: Farmasi +5 jt Paracetamol, Laboratorium −5 jt kertas → pasangan menunggu kepala unit.
        $far = $revisi->bukaDraf($this->pagu('FAR', '5.1.02'), $this->u['farmasi']);
        $revisi->simpanBaris($far, $this->u['farmasi'], [$this->ubah('Paracetamol 500 mg', 550)]);
        $far = $revisi->kirimPergeseran($far->refresh(), $this->u['farmasi'], 'Kenaikan kunjungan rawat jalan');

        $lab = $revisi->bukaDraf($this->pagu('LAB', '5.1.02'), $this->u['lab']);
        $revisi->simpanBaris($lab, $this->u['lab'], [$this->ubah('Kertas HVS A4 Lab', 500)]);
        $lab = $revisi->kirimPergeseran($lab->refresh(), $this->u['lab'], 'Sebagian formulir sudah elektronik');

        $pasangan->bentuk([$far->permintaan_id, $lab->permintaan_id], $this->u['perencana']);

        // 2) Permintaan terbuka tanpa pasangan: Radiologi melepas 1 jt tinta (bisa dipasangkan dari halaman Pergeseran).
        $rad = $revisi->bukaDraf($this->pagu('RAD', '5.1.02'), $this->u['radiologi']);
        $revisi->simpanBaris($rad, $this->u['radiologi'], [$this->ubah('Tinta printer Radiologi', 30)]);
        $revisi->kirimPergeseran($rad->refresh(), $this->u['radiologi'], 'Printer film diganti sistem PACS');

        // 3) Revisi seimbang Belanja Modal IGD → wajib persetujuan perencana (bukan BBJ).
        $igd = $revisi->bukaDraf($this->pagu('IGD', '5.2.02'), $this->u['igd']);
        $revisi->simpanBaris($igd, $this->u['igd'], [
            $this->ubah('Bed pasien ruang resusitasi', 1), $this->ubah('Bed pasien ruang observasi', 2),
        ]);
        $revisi->simpan($igd->refresh(), $this->u['igd'], 'Penataan ulang ruang observasi');

        // 4) Usulan standar harga baru dari Farmasi.
        app(UsulanStandarService::class)->ajukan(2026, [
            'uraian' => 'Ondansetron 4 mg', 'spesifikasi' => 'injeksi, ampul 2 ml', 'satuan' => 'ampul', 'harga' => '12500',
            'kategori' => 'Obat', 'konteks' => 'REVISI',
        ], $this->u['farmasi']);
    }

    /** @return array<string, mixed> */
    private function ubah(string $uraian, int $volume): array
    {
        $r = DB::table('rba_rincian as r')->join('rba as b', 'b.id', '=', 'r.rba_id')
            ->where('b.status', 'DEFINITIF')->where('r.uraian', $uraian)->where('r.aktif', true)->first(['r.*']);

        return ['aksi' => 'UBAH', 'rincian_id' => $r->id, 'row_version' => $r->row_version, 'item_id' => $r->item_id,
            'rekening_blud_id' => $r->rekening_blud_id, 'volume' => (string) $volume, 'harga' => (string) $r->harga];
    }

    private function pagu(string $unit, string $jenis): PaguJenis
    {
        return PaguJenis::query()->where('tahun', 2026)->where('unit_id', $this->unit($unit))
            ->where('jenis_id', $this->rek('PEMDA', $jenis))->firstOrFail();
    }

    private function unit(string $kode): int
    {
        return (int) DB::table('unit_kerja')->where('kode', $kode)->value('id');
    }

    private function sumberDana(string $kode): int
    {
        return (int) DB::table('sumber_dana')->where('kode', $kode)->value('id');
    }

    private function rek(string $bagan, string $kode): int
    {
        return (int) DB::table('rekening')->where('bagan', $bagan)->where('kode', $kode)->value('id');
    }
}
