<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Services;

use App\Models\User;
use App\Modules\Master\Services\AksesUnit;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Errors\KesalahanDomain;
use App\Modules\Workflow\Models\Persetujuan;
use App\Modules\Workflow\Models\WorkflowDefinisi;
use App\Modules\Workflow\Models\WorkflowLangkah;
use Illuminate\Support\Facades\DB;

/**
 * Mesin persetujuan generik (TDD bagian 5): langkah berurutan, permission per langkah,
 * cakupan unit, delegasi/Plt, pemisahan tugas, dan persetujuan otomatis berbatas waktu.
 * Seluruh metode mutasi dipanggil di dalam transaksi milik service domain.
 */
final class WorkflowService
{
    public function __construct(
        private readonly AksesUnit $akses,
        private readonly AuditService $audit,
    ) {}

    public function mulai(string $objekTipe, int $objekId, int $pengusulId, ?string $nilai = null, ?int $unitId = null): Persetujuan
    {
        /** @var WorkflowDefinisi|null $def */
        $def = WorkflowDefinisi::query()->where('objek_tipe', $objekTipe)->where('aktif', true)->first();
        if ($def === null) {
            throw new KesalahanDomain('WORKFLOW_BELUM_DIATUR', "Alur persetujuan untuk {$objekTipe} belum dikonfigurasi.");
        }
        $langkahPertama = $this->langkahBerlaku($def->id, $nilai, 0);
        if ($langkahPertama === null) {
            throw new KesalahanDomain('WORKFLOW_BELUM_DIATUR', "Alur persetujuan {$objekTipe} tidak memiliki langkah untuk nilai ini.");
        }

        $p = Persetujuan::query()->forceCreate([
            'objek_tipe' => $objekTipe, 'objek_id' => $objekId, 'definisi_id' => $def->id,
            'langkah_aktif' => $langkahPertama->urutan, 'nilai' => $nilai, 'unit_id' => $unitId,
            'diajukan_oleh' => $pengusulId, 'status' => 'BERJALAN',
        ]);
        $p->aksi()->create(['urutan_langkah' => null, 'aksi' => 'AJUKAN', 'pelaku_id' => $pengusulId, 'kanal' => 'WEB']);

        return $p;
    }

    public function berjalan(string $objekTipe, int $objekId): ?Persetujuan
    {
        return Persetujuan::query()->where('objek_tipe', $objekTipe)->where('objek_id', $objekId)
            ->where('status', 'BERJALAN')->lockForUpdate()->first();
    }

    /**
     * @return bool true bila persetujuan selesai (langkah terakhir disetujui)
     */
    public function setujui(Persetujuan $p, User $user, ?string $catatan = null, string $kanal = 'WEB', ?string $metode = null): bool
    {
        $p = $this->kunci($p);
        $langkah = $this->langkahAktif($p);
        $atasNama = $this->otorisasi($p, $langkah, $user);

        $this->catatAksi($p, $langkah, 'SETUJUI', $user->id, $atasNama, $catatan, $kanal, $metode);

        $berikut = $this->langkahBerlaku($p->definisi_id, $p->nilai, $langkah->urutan);
        if ($berikut === null) {
            $p->forceFill(['status' => 'DISETUJUI', 'langkah_aktif' => null])->save();

            return true;
        }
        $p->forceFill(['langkah_aktif' => $berikut->urutan])->save();

        return false;
    }

    public function tolak(Persetujuan $p, User $user, string $catatan, bool $kembalikan = false, string $kanal = 'WEB', ?string $metode = null): void
    {
        $min = (int) config('blud.persetujuan.catatan_minimum_karakter', 10);
        if (mb_strlen(trim($catatan)) < $min) {
            throw new KesalahanDomain('CATATAN_WAJIB', "Catatan penolakan/pengembalian minimal {$min} karakter.");
        }
        $p = $this->kunci($p);
        $langkah = $this->langkahAktif($p);
        $atasNama = $this->otorisasi($p, $langkah, $user);

        $this->catatAksi($p, $langkah, $kembalikan ? 'KEMBALIKAN' : 'TOLAK', $user->id, $atasNama, $catatan, $kanal, $metode);
        $p->forceFill(['status' => $kembalikan ? 'DIKEMBALIKAN' : 'DITOLAK', 'langkah_aktif' => null])->save();
    }

    /** Persetujuan otomatis setelah batas waktu (PRD 7.8a butir 9). Hanya untuk langkah yang mengizinkan. */
    public function setujuiOtomatis(Persetujuan $p, string $alasan): bool
    {
        $p = $this->kunci($p);
        $langkah = $this->langkahAktif($p);
        if (! $langkah->boleh_otomatis) {
            throw new KesalahanDomain('TIDAK_BOLEH_OTOMATIS', "Langkah {$langkah->nama} tidak mengizinkan persetujuan otomatis.");
        }
        $this->catatAksi($p, $langkah, 'SETUJUI_OTOMATIS', null, null, $alasan, 'SISTEM', null);

        $berikut = $this->langkahBerlaku($p->definisi_id, $p->nilai, $langkah->urutan);
        $p->forceFill($berikut === null ? ['status' => 'DISETUJUI', 'langkah_aktif' => null] : ['langkah_aktif' => $berikut->urutan])->save();

        return $berikut === null;
    }

    public function batalkan(Persetujuan $p, ?int $userId, string $alasan): void
    {
        $p = $this->kunci($p);
        $p->aksi()->create(['aksi' => 'BATALKAN', 'pelaku_id' => $userId, 'kanal' => $userId ? 'WEB' : 'SISTEM', 'catatan' => $alasan]);
        $p->forceFill(['status' => 'DIBATALKAN', 'langkah_aktif' => null])->save();
    }

    /** Pengguna yang berhak memutuskan langkah aktif (untuk notifikasi dan kotak masuk). */
    public function bolehMemutuskan(Persetujuan $p, User $user): bool
    {
        try {
            $this->otorisasi($p, $this->langkahAktif($p), $user);

            return true;
        } catch (KesalahanDomain) {
            return false;
        }
    }

    private function kunci(Persetujuan $p): Persetujuan
    {
        /** @var Persetujuan $terkunci */
        $terkunci = Persetujuan::query()->whereKey($p->id)->lockForUpdate()->firstOrFail();
        if ($terkunci->status !== 'BERJALAN') {
            throw new KesalahanDomain('PERSETUJUAN_SELESAI', "Persetujuan ini sudah berstatus {$terkunci->status}.", statusHttp: 409);
        }

        return $terkunci;
    }

    private function langkahAktif(Persetujuan $p): WorkflowLangkah
    {
        /** @var WorkflowLangkah */
        return WorkflowLangkah::query()->where('definisi_id', $p->definisi_id)->where('urutan', $p->langkah_aktif)->firstOrFail();
    }

    private function langkahBerlaku(int $definisiId, ?string $nilai, int $setelahUrutan): ?WorkflowLangkah
    {
        return WorkflowLangkah::query()->where('definisi_id', $definisiId)->where('urutan', '>', $setelahUrutan)
            ->when($nilai !== null, fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('nilai_minimum')->orWhere('nilai_minimum', '<=', $nilai))
                ->where(fn ($w) => $w->whereNull('nilai_maksimum')->orWhere('nilai_maksimum', '>=', $nilai)))
            ->orderBy('urutan')->first();
    }

    /**
     * Memastikan pengguna boleh memutuskan langkah: punya permission (langsung atau sebagai delegasi),
     * dalam cakupan unit, dan bukan pengusul/penyetuju sebelumnya (pemisahan tugas, UAT-27).
     *
     * @return int|null id pejabat definitif bila bertindak sebagai delegasi
     */
    private function otorisasi(Persetujuan $p, WorkflowLangkah $langkah, User $user): ?int
    {
        if ((int) $p->diajukan_oleh === (int) $user->id) {
            throw new KesalahanDomain('PEMISAHAN_TUGAS', 'Pengusul tidak dapat menyetujui pengajuannya sendiri.', statusHttp: 403);
        }
        $sudahBertindak = $p->aksi()->where('aksi', 'SETUJUI')->where('pelaku_id', $user->id)->exists();
        if ($sudahBertindak) {
            throw new KesalahanDomain('PEMISAHAN_TUGAS', 'Pengguna yang sama tidak dapat menyetujui dua langkah pada pengajuan yang sama.', statusHttp: 403);
        }

        $atasNama = null;
        if (! $user->can($langkah->permission)) {
            $atasNama = DB::table('delegasi')
                ->where('pelaksana_id', $user->id)
                ->whereRaw('berlaku @> now()')
                ->whereRaw('? = ANY (kewenangan)', [$langkah->permission])
                ->value('pejabat_id');
            if ($atasNama === null) {
                throw new KesalahanDomain('TIDAK_BERWENANG', "Anda tidak berwenang pada langkah {$langkah->nama}.", statusHttp: 403);
            }
            $atasNama = (int) $atasNama;
        }

        if ($langkah->cakupan === 'UNIT_PENGUSUL' && $p->unit_id !== null
            && ! $this->akses->milik($atasNama ?? $user->id, (int) $p->unit_id)) {
            throw new KesalahanDomain('DI_LUAR_CAKUPAN_UNIT', 'Pengajuan ini berada di luar cakupan unit Anda.', statusHttp: 403);
        }

        return $atasNama;
    }

    private function catatAksi(Persetujuan $p, WorkflowLangkah $langkah, string $aksi, ?int $pelaku, ?int $atasNama, ?string $catatan, string $kanal, ?string $metode): void
    {
        $p->aksi()->create([
            'urutan_langkah' => $langkah->urutan, 'aksi' => $aksi, 'pelaku_id' => $pelaku, 'atas_nama_id' => $atasNama,
            'kanal' => $kanal, 'metode_konfirmasi' => $metode, 'catatan' => $catatan,
        ]);
        if ($atasNama !== null) {
            DB::select("SELECT set_config('app.atas_nama_id', ?, true)", [(string) $atasNama]);
        }
        $this->audit->tindakan($aksi, 'persetujuan', $p->id, $catatan, ['langkah' => $langkah->nama, 'kanal' => $kanal], sistem: $pelaku === null);
    }
}
