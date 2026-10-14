<?php

declare(strict_types=1);

namespace App\Modules\Pergeseran\Services;

use App\Models\User;
use App\Modules\Anggaran\Models\PaguJenis;
use App\Modules\Anggaran\Models\RbaRincian;
use App\Modules\Anggaran\Services\PaguLedger;
use App\Modules\Kodefikasi\Services\PemetaanService;
use App\Modules\Master\Services\AksesUnit;
use App\Modules\Pergeseran\Domain\AnggotaPasangan;
use App\Modules\Pergeseran\Domain\ValidatorPasangan;
use App\Modules\Pergeseran\Models\PasanganPergeseran;
use App\Modules\Pergeseran\Models\PermintaanPergeseran;
use App\Modules\Revisi\Domain\KalkulatorRevisi;
use App\Modules\Revisi\Models\RevisiDetail;
use App\Modules\Revisi\Models\RevisiPaket;
use App\Modules\Revisi\Services\RevisiService;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Domain\Uang;
use App\Modules\Shared\Errors\KesalahanDomain;
use App\Modules\Shared\Konfigurasi;
use App\Modules\Shared\Notifikasi\NotifikasiBlud;
use App\Modules\Shared\Notifikasi\Penerima;
use App\Modules\Shared\Penomoran\PenomoranService;
use App\Modules\Shared\Support\Kalender;
use App\Modules\Shared\Support\Kunci;
use App\Modules\Workflow\Services\WorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Pasangan pergeseran antarunit (PRD 7.8a, TDD 4.5):
 * bentuk -> persetujuan unit anggota -> MENUNGGU_PERENCANA (batas N hari kerja) -> sahkan / tolak / disetujui otomatis.
 */
final class PasanganService
{
    public const WORKFLOW = 'PASANGAN_PERGESERAN';

    public function __construct(
        private readonly ValidatorPasangan $validator,
        private readonly PermintaanService $permintaan,
        private readonly RevisiService $revisi,
        private readonly PaguLedger $ledger,
        private readonly WorkflowService $workflow,
        private readonly PemetaanService $pemetaan,
        private readonly AksesUnit $akses,
        private readonly Kalender $kalender,
        private readonly Konfigurasi $konfigurasi,
        private readonly PenomoranService $penomoran,
        private readonly AuditService $audit,
        private readonly Penerima $penerima,
    ) {}

    /** @param list<int> $permintaanIds */
    public function bentuk(array $permintaanIds, User $user): PasanganPergeseran
    {
        $bolehPerencana = $user->can('pergeseran.sahkan');
        if (! $bolehPerencana && ! $user->can('pergeseran.ajukan')) {
            throw new KesalahanDomain('TIDAK_BERWENANG', 'Anda tidak berwenang membentuk pasangan pergeseran.', statusHttp: 403);
        }

        return DB::transaction(function () use ($permintaanIds, $user, $bolehPerencana) {
            $permintaan = Kunci::baris(PermintaanPergeseran::class, $permintaanIds);
            if ($permintaan->count() !== count(array_unique($permintaanIds))) {
                throw new KesalahanDomain('PERMINTAAN_TIDAK_DITEMUKAN', 'Sebagian permintaan tidak ditemukan.');
            }
            $pagu = PaguJenis::query()->whereIn('id', $permintaan->pluck('pagu_jenis_id'))->get()->keyBy('id');

            if (! $bolehPerencana) {
                // Pengusul hanya dapat memasangkan bila salah satu permintaan adalah miliknya.
                $milik = $permintaan->contains(fn (PermintaanPergeseran $m) => (int) $m->diajukan_oleh === (int) $user->id);
                if (! $milik) {
                    throw new KesalahanDomain('TIDAK_BERWENANG', 'Pengusul hanya dapat memasangkan permintaan unitnya sendiri.', statusHttp: 403);
                }
            }

            $anggota = $permintaan->values()->map(fn (PermintaanPergeseran $m) => new AnggotaPasangan(
                permintaanId: $m->id, arah: $m->arah, nilai: Uang::dari((string) $m->nilai),
                unitId: $pagu[$m->pagu_jenis_id]->unit_id, jenisId: $pagu[$m->pagu_jenis_id]->jenis_id,
                sumberDanaId: $pagu[$m->pagu_jenis_id]->sumber_dana_id, tahun: $pagu[$m->pagu_jenis_id]->tahun,
                status: $m->status, batasPasangan: $m->batas_pasangan, memuatDiAtasStandar: $m->memuat_di_atas_standar,
            ))->all();

            $hasil = $this->validator->periksa($anggota, now()->toDateTimeImmutable());
            if (! $hasil['valid']) {
                throw new KesalahanDomain('PASANGAN_TIDAK_VALID', $hasil['galat'][0], ['galat' => $hasil['galat']]);
            }

            /** @var PasanganPergeseran $pasangan */
            $pasangan = PasanganPergeseran::query()->forceCreate([
                'nomor' => $this->penomoran->berikutnya('PASANGAN', (int) $hasil['tahun']),
                'tahun' => $hasil['tahun'], 'jenis_id' => $hasil['jenis_id'], 'sumber_dana_id' => $hasil['sumber_dana_id'],
                'total_tambah' => $hasil['total_tambah']->keDesimal(), 'total_kurang' => $hasil['total_kurang']->keDesimal(),
                'memuat_di_atas_standar' => $hasil['memuat_di_atas_standar'], 'status' => 'MENUNGGU_UNIT', 'dibentuk_oleh' => $user->id,
            ]);

            foreach ($permintaan as $m) {
                // Pengusul permintaan sudah menyatakan kesediaan dengan mengajukannya; unit lain wajib menyetujui.
                $setuju = (int) $m->diajukan_oleh === (int) $user->id;
                DB::table('pasangan_anggota')->insert([
                    'pasangan_id' => $pasangan->id, 'permintaan_id' => $m->id,
                    'persetujuan_unit' => $setuju ? 'SETUJU' : 'MENUNGGU',
                    'diputuskan_oleh' => $setuju ? $user->id : null, 'diputuskan_pada' => $setuju ? now() : null,
                ]);
                $m->forceFill(['status' => 'BERPASANGAN'])->save();
            }

            $this->audit->tindakan('BENTUK_PASANGAN', 'pasangan_pergeseran', $pasangan->id, null, ['permintaan' => $permintaanIds]);
            $this->lengkapkanBilaSemuaSetuju($pasangan);
            $this->beritahuAnggotaMenunggu($pasangan);

            return $pasangan->refresh();
        }, attempts: 3);
    }

    /** Persetujuan unit anggota atas keterlibatannya (PRD 7.8a butir 4). */
    public function putuskanUnit(PasanganPergeseran $pasangan, int $permintaanId, User $user, bool $setuju, ?string $catatan = null): PasanganPergeseran
    {
        return DB::transaction(function () use ($pasangan, $permintaanId, $user, $setuju, $catatan) {
            $pasangan = $this->kunci($pasangan, ['MENUNGGU_UNIT']);
            $m = PermintaanPergeseran::query()->findOrFail($permintaanId);
            $unitId = (int) PaguJenis::query()->whereKey($m->pagu_jenis_id)->value('unit_id');

            if (! $user->can('pergeseran.setujui_unit') || ! $this->akses->milik($user->id, $unitId)) {
                throw new KesalahanDomain('TIDAK_BERWENANG', 'Anda tidak berwenang memutuskan untuk unit ini.', statusHttp: 403);
            }
            $baris = DB::table('pasangan_anggota')->where('pasangan_id', $pasangan->id)->where('permintaan_id', $permintaanId)
                ->where('aktif', true)->lockForUpdate()->first();
            if ($baris === null || $baris->persetujuan_unit !== 'MENUNGGU') {
                throw new KesalahanDomain('STATUS_TIDAK_SESUAI', 'Keputusan unit untuk permintaan ini sudah diberikan.', statusHttp: 409);
            }

            DB::table('pasangan_anggota')->where('pasangan_id', $pasangan->id)->where('permintaan_id', $permintaanId)->update([
                'persetujuan_unit' => $setuju ? 'SETUJU' : 'TOLAK', 'diputuskan_oleh' => $user->id, 'diputuskan_pada' => now(),
            ]);
            $this->audit->tindakan($setuju ? 'SETUJU_UNIT' : 'TOLAK_UNIT', 'pasangan_pergeseran', $pasangan->id, $catatan, ['permintaan_id' => $permintaanId]);

            if (! $setuju) {
                $this->bubarkan($pasangan, 'DITOLAK', 'Unit anggota menolak pasangan'.($catatan ? ': '.$catatan : ''));

                return $pasangan->refresh();
            }
            $this->lengkapkanBilaSemuaSetuju($pasangan);

            return $pasangan->refresh();
        }, attempts: 3);
    }

    /**
     * Pengesahan pasangan. $user = null berarti persetujuan otomatis oleh sistem setelah batas waktu.
     * Pagu seluruh unit dan paket revisi terkait diterapkan dalam satu transaksi (FR-045).
     */
    public function sahkan(PasanganPergeseran $pasangan, ?User $user, ?string $catatan = null, string $kanal = 'WEB', ?string $metode = null): PasanganPergeseran
    {
        $otomatis = $user === null;

        return DB::transaction(function () use ($pasangan, $user, $catatan, $otomatis, $kanal, $metode) {
            $pasangan = $this->kunci($pasangan, ['MENUNGGU_PERENCANA']);
            $wf = $this->workflow->berjalan(self::WORKFLOW, $pasangan->id)
                ?? throw new KesalahanDomain('WORKFLOW_TIDAK_DITEMUKAN', 'Alur persetujuan pasangan tidak ditemukan.');

            if ($otomatis) {
                if (! $this->bolehOtomatis($pasangan)) {
                    throw new KesalahanDomain('TIDAK_BOLEH_OTOMATIS', 'Pasangan ini wajib diputuskan perencana.');
                }
                AuditService::sebagaiSistem();
            }

            // Kunci pagu (urut id) dan validasi ulang sebelum menyentuh data.
            $anggota = $pasangan->permintaan()->get();
            $paguIds = $anggota->pluck('pagu_jenis_id')->all();
            $pagu = Kunci::baris(PaguJenis::class, $paguIds);
            $rincianIds = RevisiDetail::query()->whereIn('paket_id', $anggota->pluck('revisi_paket_id')->filter())->whereNotNull('rincian_id')->pluck('rincian_id');
            Kunci::baris(RbaRincian::class, $rincianIds);

            $galat = $this->validasiUlang($pasangan, $anggota, $pagu);
            if ($galat !== null) {
                if ($otomatis) {
                    $this->workflow->batalkan($wf, null, 'Validasi ulang gagal: '.$galat);
                    $this->bubarkan($pasangan, 'DITOLAK_VALIDASI', 'Validasi ulang saat batas pengesahan gagal: '.$galat, permintaanDitolak: true);

                    return $pasangan->refresh();
                }
                throw new KesalahanDomain('VALIDASI_ULANG_GAGAL', $galat, statusHttp: 409);
            }

            $selesai = $otomatis
                ? $this->workflow->setujuiOtomatis($wf, 'Batas waktu pengesahan terlampaui')
                : $this->workflow->setujui($wf, $user, $catatan, $kanal, $metode);
            if (! $selesai) {
                return $pasangan;   // masih ada langkah persetujuan berikutnya
            }

            $totalPerubahan = Uang::nol();
            foreach ($anggota as $m) {
                $nilai = Uang::dari((string) $m->nilai);
                if ($m->arah === 'KURANG') {
                    $this->ledger->catat($m->pagu_jenis_id, PaguLedger::TERCADANG, $nilai->negatif(), 'PASANGAN', $pasangan->id, $pasangan->nomor);
                    $this->ledger->catat($m->pagu_jenis_id, PaguLedger::PAGU, $nilai->negatif(), 'PASANGAN', $pasangan->id, $pasangan->nomor);
                    $totalPerubahan = $totalPerubahan->kurang($nilai);
                } else {
                    $this->ledger->catat($m->pagu_jenis_id, PaguLedger::PAGU, $nilai, 'PASANGAN', $pasangan->id, $pasangan->nomor);
                    $totalPerubahan = $totalPerubahan->tambah($nilai);
                }
            }
            if (! $totalPerubahan->adalahNol()) {
                throw new \LogicException('Invarian dilanggar: total perubahan pagu pasangan bukan nol');
            }

            foreach ($anggota as $m) {
                if ($m->revisi_paket_id !== null) {
                    $this->revisi->terapkan(RevisiPaket::query()->findOrFail($m->revisi_paket_id));
                }
                $m->forceFill(['status' => 'DISAHKAN'])->save();
            }

            $pasangan->forceFill([
                'status' => $otomatis ? 'DISETUJUI_OTOMATIS' : 'DISAHKAN',
                'diputuskan_oleh' => $user?->id, 'diputuskan_pada' => now(), 'alasan' => $catatan,
            ])->save();
            $this->audit->tindakan($otomatis ? 'SETUJUI_OTOMATIS' : 'SAHKAN_PASANGAN', 'pasangan_pergeseran', $pasangan->id,
                $otomatis ? 'Disetujui otomatis oleh sistem — batas waktu pengesahan terlampaui' : $catatan, sistem: $otomatis);

            DB::afterCommit(fn () => $this->beritahuHasil($pasangan->refresh()));

            return $pasangan->refresh();
        }, attempts: 3);
    }

    public function tolak(PasanganPergeseran $pasangan, User $user, string $catatan, string $kanal = 'WEB', ?string $metode = null): PasanganPergeseran
    {
        return DB::transaction(function () use ($pasangan, $user, $catatan, $kanal, $metode) {
            $pasangan = $this->kunci($pasangan, ['MENUNGGU_PERENCANA']);
            $wf = $this->workflow->berjalan(self::WORKFLOW, $pasangan->id)
                ?? throw new KesalahanDomain('WORKFLOW_TIDAK_DITEMUKAN', 'Alur persetujuan pasangan tidak ditemukan.');
            $this->workflow->tolak($wf, $user, $catatan, false, $kanal, $metode);
            $pasangan->forceFill(['diputuskan_oleh' => $user->id, 'diputuskan_pada' => now()])->save();
            $this->bubarkan($pasangan, 'DITOLAK', 'Ditolak perencana: '.$catatan);

            return $pasangan->refresh();
        }, attempts: 3);
    }

    public function bolehOtomatis(PasanganPergeseran $pasangan): bool
    {
        return ValidatorPasangan::bolehOtomatis(
            $pasangan->memuat_di_atas_standar,
            $this->pemetaan->kodeJenis($pasangan->jenis_id),
            (bool) $this->konfigurasi->get('pergeseran.persetujuan_otomatis_aktif', true),
            $this->konfigurasi->get('pergeseran.jenis_boleh_otomatis'),
        );
    }

    /**
     * Membubarkan pasangan. Permintaan kembali TERBUKA dengan batas waktu awal (asumsi T-2),
     * kecuali $permintaanDitolak = true atau batasnya sudah lewat.
     */
    public function bubarkan(PasanganPergeseran $pasangan, string $status, string $alasan, bool $permintaanDitolak = false): void
    {
        $anggota = $pasangan->permintaan()->get();
        DB::table('pasangan_anggota')->where('pasangan_id', $pasangan->id)->update(['aktif' => false]);
        $pasangan->forceFill(['status' => $status, 'alasan' => $alasan])->save();

        foreach ($anggota as $m) {
            $m = $this->permintaan->kunci($m);
            if ($permintaanDitolak || $m->batas_pasangan->lessThan(now())) {
                $this->permintaan->akhiri($m, $permintaanDitolak ? 'DITOLAK' : 'DITOLAK_OTOMATIS', $alasan);
            } else {
                $m->forceFill(['status' => 'TERBUKA'])->save();
            }
        }
        $this->audit->tindakan($status, 'pasangan_pergeseran', $pasangan->id, $alasan);
    }

    // ------------------------------------------------------------------ internal

    /** @param list<string> $statusDiizinkan */
    private function kunci(PasanganPergeseran $p, array $statusDiizinkan): PasanganPergeseran
    {
        /** @var PasanganPergeseran $p */
        $p = PasanganPergeseran::query()->whereKey($p->id)->lockForUpdate()->firstOrFail();
        if (! in_array($p->status, $statusDiizinkan, true)) {
            throw new KesalahanDomain('STATUS_TIDAK_SESUAI', "Pasangan berstatus {$p->status}.", statusHttp: 409);
        }

        return $p;
    }

    private function lengkapkanBilaSemuaSetuju(PasanganPergeseran $pasangan): void
    {
        $menunggu = DB::table('pasangan_anggota')->where('pasangan_id', $pasangan->id)->where('aktif', true)
            ->where('persetujuan_unit', '<>', 'SETUJU')->exists();
        if ($menunggu) {
            return;
        }

        $sekarang = now();
        $batas = $this->kalender->kerja()->akhirHariKerjaKe(
            $sekarang->toDateTimeImmutable(), (int) $this->konfigurasi->get('pergeseran.batas_pengesahan_hari_kerja', 5)
        );
        $pasangan->forceFill(['status' => 'MENUNGGU_PERENCANA', 'lengkap_pada' => $sekarang, 'batas_pengesahan' => $batas])->save();
        // Pengusul pada alur persetujuan = pengusul permintaan TAMBAH (unit penerima manfaat), bukan pembentuk pasangan,
        // sehingga perencana yang memasangkan tetap dapat mengesahkan; pengusul unit tetap tidak dapat menyetujui sendiri.
        $pengusul = (int) PermintaanPergeseran::query()
            ->whereIn('id', DB::table('pasangan_anggota')->where('pasangan_id', $pasangan->id)->where('aktif', true)->select('permintaan_id'))
            ->where('arah', 'TAMBAH')->orderBy('id')->value('diajukan_oleh');
        $this->workflow->mulai(self::WORKFLOW, $pasangan->id, $pengusul ?: (int) $pasangan->dibentuk_oleh, (string) $pasangan->total_tambah);

        DB::afterCommit(function () use ($pasangan, $batas) {
            $teks = $pasangan->memuat_di_atas_standar
                ? 'Memuat harga di atas standar — wajib diputuskan perencana.'
                : 'Bila tidak diputuskan sampai '.$batas->format('d-m-Y H:i').' WIB, pasangan akan disetujui otomatis.';
            Notification::send($this->penerima->denganPermission('pergeseran.sahkan'),
                new NotifikasiBlud('PASANGAN_MENUNGGU', "Pasangan pergeseran {$pasangan->nomor} menunggu pengesahan",
                    $teks, "/pergeseran/pasangan/{$pasangan->uuid}"));
        });
    }

    /** @return string|null pesan galat pertama, atau null bila valid */
    private function validasiUlang(PasanganPergeseran $pasangan, $anggota, $pagu): ?string
    {
        // Batas pasangan tidak berlaku lagi setelah pasangan terbentuk: anggota diberi batas jauh di depan
        // memakai jam aplikasi (bukan jam sistem) agar konsisten dengan $sekarang.
        $tanpaBatas = now()->addYear()->toDateTimeImmutable();
        $anggotaDomain = $anggota->map(fn (PermintaanPergeseran $m) => new AnggotaPasangan(
            $m->id, $m->arah, Uang::dari((string) $m->nilai), $pagu[$m->pagu_jenis_id]->unit_id, $pagu[$m->pagu_jenis_id]->jenis_id,
            $pagu[$m->pagu_jenis_id]->sumber_dana_id, $pagu[$m->pagu_jenis_id]->tahun, 'TERBUKA',
            $tanpaBatas, $m->memuat_di_atas_standar,
        ))->values()->all();
        $hasil = $this->validator->periksa($anggotaDomain, now()->toDateTimeImmutable());
        if (! $hasil['valid']) {
            return $hasil['galat'][0];
        }

        $belumSetuju = DB::table('pasangan_anggota')->where('pasangan_id', $pasangan->id)->where('aktif', true)
            ->where('persetujuan_unit', '<>', 'SETUJU')->exists();
        if ($belumSetuju) {
            return 'Masih ada unit anggota yang belum menyetujui.';
        }

        foreach ($anggota as $m) {
            $p = $pagu[$m->pagu_jenis_id];
            if ($m->status !== 'BERPASANGAN') {
                return "Permintaan {$m->nomor} berstatus {$m->status}.";
            }
            if ($m->arah === 'KURANG' && Uang::dari((string) $p->tercadang)->lebihKecil(Uang::dari((string) $m->nilai))) {
                return "Pencadangan permintaan {$m->nomor} tidak lagi utuh.";
            }
            if ($m->revisi_paket_id === null) {
                return "Permintaan {$m->nomor} tidak memiliki paket revisi.";
            }

            // Paket revisi harus seimbang terhadap pagu SETELAH pergeseran.
            $paket = RevisiPaket::query()->findOrFail($m->revisi_paket_id);
            $delta = Uang::dari((string) $m->nilai);
            $paguSetelah = (clone $p);
            $paguSetelah->pagu_berlaku = ($m->arah === 'KURANG' ? $p->paguBerlaku()->kurang($delta) : $p->paguBerlaku()->tambah($delta))->keDesimal();
            $hasilRevisi = $this->revisi->hitungUntukPagu($paket, $paguSetelah);
            if ($hasilRevisi->pelanggaran !== []) {
                return "Revisi {$paket->nomor}: ".$hasilRevisi->pelanggaran[0]->pesan;
            }
            if ($hasilRevisi->status !== KalkulatorRevisi::SEIMBANG) {
                return "Revisi {$paket->nomor} tidak lagi sesuai dengan nilai pergeseran (rincian berubah sejak diajukan).";
            }
        }

        return null;
    }

    private function beritahuAnggotaMenunggu(PasanganPergeseran $pasangan): void
    {
        $menunggu = DB::table('pasangan_anggota as a')->join('permintaan_pergeseran as m', 'm.id', '=', 'a.permintaan_id')
            ->join('pagu_jenis as g', 'g.id', '=', 'm.pagu_jenis_id')
            ->where('a.pasangan_id', $pasangan->id)->where('a.aktif', true)->where('a.persetujuan_unit', 'MENUNGGU')
            ->pluck('g.unit_id')->all();
        if ($menunggu === []) {
            return;
        }
        DB::afterCommit(fn () => Notification::send(
            $this->penerima->denganPermission('pergeseran.setujui_unit', $menunggu),
            new NotifikasiBlud('PASANGAN_PERLU_PERSETUJUAN_UNIT', "Unit Anda dilibatkan dalam pasangan {$pasangan->nomor}",
                'Mohon setujui atau tolak keterlibatan unit Anda.', "/pergeseran/pasangan/{$pasangan->uuid}")
        ));
    }

    private function beritahuHasil(PasanganPergeseran $pasangan): void
    {
        $pengusul = PermintaanPergeseran::query()->whereIn('id', DB::table('pasangan_anggota')->where('pasangan_id', $pasangan->id)->select('permintaan_id'))
            ->pluck('diajukan_oleh')->unique()->all();
        $penerima = User::query()->whereIn('id', $pengusul)->get()->merge($this->penerima->denganPermission('pergeseran.sahkan'))->unique('id');
        $judul = $pasangan->status === 'DISETUJUI_OTOMATIS'
            ? "Pasangan {$pasangan->nomor} disetujui otomatis oleh sistem"
            : "Pasangan {$pasangan->nomor} disahkan";
        Notification::send($penerima, new NotifikasiBlud('PASANGAN_'.$pasangan->status, $judul,
            'Pagu unit dan rincian terkait telah diperbarui.', "/pergeseran/pasangan/{$pasangan->uuid}"));
    }
}
