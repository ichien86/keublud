<?php

declare(strict_types=1);

namespace App\Modules\Pergeseran\Services;

use App\Models\User;
use App\Modules\Anggaran\Models\PaguJenis;
use App\Modules\Anggaran\Models\RbaRincian;
use App\Modules\Anggaran\Services\PaguLedger;
use App\Modules\Pergeseran\Models\PermintaanPergeseran;
use App\Modules\Revisi\Models\RevisiPaket;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Domain\Uang;
use App\Modules\Shared\Errors\KesalahanDomain;
use App\Modules\Shared\Konfigurasi;
use App\Modules\Shared\Notifikasi\NotifikasiBlud;
use App\Modules\Shared\Notifikasi\Penerima;
use App\Modules\Shared\Penomoran\PenomoranService;
use App\Modules\Shared\Support\Kalender;
use App\Modules\Shared\Support\Kunci;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Permintaan tambah/kurang pergeseran antarunit (PRD 7.8, 7.8a; TDD 4.5).
 * - KURANG: nilai dicadangkan di pagu unit sejak diajukan.
 * - Batas pasangan: akhir hari kerja ke-N (default 5) sejak pengajuan; lewat batas -> ditolak otomatis.
 * Setiap permintaan wajib berasal dari paket revisi, agar rincian dan pagu selalu bergerak bersama.
 */
final class PermintaanService
{
    public function __construct(
        private readonly PaguLedger $ledger,
        private readonly Kalender $kalender,
        private readonly Konfigurasi $konfigurasi,
        private readonly PenomoranService $penomoran,
        private readonly AuditService $audit,
        private readonly Penerima $penerima,
    ) {}

    /** Wajib dipanggil di dalam transaksi; pagu sudah dikunci oleh pemanggil. */
    public function buat(PaguJenis $pagu, string $arah, Uang $nilai, User $user, RevisiPaket $paket, bool $memuatDiAtasStandar): PermintaanPergeseran
    {
        if (! $user->can('pergeseran.ajukan')) {
            throw new KesalahanDomain('TIDAK_BERWENANG', 'Anda tidak berwenang mengajukan pergeseran.', statusHttp: 403);
        }
        if (! in_array($arah, ['TAMBAH', 'KURANG'], true) || ! $nilai->positif()) {
            throw new KesalahanDomain('PERMINTAAN_TIDAK_VALID', 'Arah atau nilai permintaan tidak valid.');
        }

        $sekarang = now();
        $batas = $this->kalender->kerja()->akhirHariKerjaKe(
            $sekarang->toDateTimeImmutable(), (int) $this->konfigurasi->get('pergeseran.batas_pasangan_hari_kerja', 5)
        );

        /** @var PermintaanPergeseran $m */
        $m = PermintaanPergeseran::query()->forceCreate([
            'nomor' => $this->penomoran->berikutnya('PERMINTAAN', $pagu->tahun, (string) DB::table('unit_kerja')->where('id', $pagu->unit_id)->value('kode')),
            'arah' => $arah, 'pagu_jenis_id' => $pagu->id, 'nilai' => $nilai->keDesimal(),
            'revisi_paket_id' => $paket->id, 'memuat_di_atas_standar' => $memuatDiAtasStandar,
            'status' => 'TERBUKA', 'diajukan_oleh' => $user->id, 'diajukan_pada' => $sekarang, 'batas_pasangan' => $batas,
        ]);

        if ($arah === 'KURANG') {
            // Pencadangan (FR-041): sisa unit asal langsung berkurang; gagal bila sisa tidak cukup.
            $this->ledger->catat($pagu->id, PaguLedger::TERCADANG, $nilai, 'PERMINTAAN_KURANG', $m->id, 'Pencadangan permintaan '.$m->nomor);
        }

        $this->audit->tindakan('AJUKAN_PERMINTAAN', 'permintaan_pergeseran', $m->id, null, ['arah' => $arah, 'nilai' => $nilai->keDesimal()]);

        DB::afterCommit(fn () => $this->beritahuUnitSejenis($m));

        return $m;
    }

    public function batalkan(PermintaanPergeseran $m, User $user): void
    {
        DB::transaction(function () use ($m, $user) {
            $m = $this->kunci($m);
            if ((int) $m->diajukan_oleh !== (int) $user->id) {
                throw new KesalahanDomain('BUKAN_PENGUSUL', 'Hanya pengusul yang dapat membatalkan permintaan.', statusHttp: 403);
            }
            if ($m->status !== 'TERBUKA') {
                throw new KesalahanDomain('STATUS_TIDAK_SESUAI', 'Permintaan yang sudah berpasangan tidak dapat dibatalkan; minta perencana menolak pasangannya.', statusHttp: 409);
            }
            $this->akhiri($m, 'DIBATALKAN', 'Dibatalkan pengusul');
        }, attempts: 3);
    }

    /**
     * Mengakhiri permintaan yang belum disahkan: melepas pencadangan dan menggugurkan paket revisi.
     * Wajib di dalam transaksi; permintaan sudah dikunci.
     */
    public function akhiri(PermintaanPergeseran $m, string $status, string $alasan): void
    {
        if (! in_array($m->status, ['TERBUKA', 'BERPASANGAN'], true)) {
            return;   // idempoten
        }
        if ($m->arah === 'KURANG') {
            Kunci::baris(PaguJenis::class, [$m->pagu_jenis_id]);
            $this->ledger->catat($m->pagu_jenis_id, PaguLedger::TERCADANG, Uang::dari((string) $m->nilai)->negatif(),
                'LEPAS_PENCADANGAN', $m->id, $alasan);
        }
        $m->forceFill(['status' => $status, 'alasan_tolak' => $alasan])->save();

        if ($m->revisi_paket_id !== null) {
            /** @var RevisiPaket $paket */
            $paket = RevisiPaket::query()->whereKey($m->revisi_paket_id)->lockForUpdate()->first();
            if ($paket !== null && $paket->status === 'MENUNGGU_PERGESERAN') {
                RbaRincian::query()->where('terkunci_paket_id', $paket->id)->update(['terkunci_paket_id' => null]);
                $paket->forceFill(['status' => 'GUGUR', 'alasan' => trim(($paket->alasan ? $paket->alasan.' | ' : '').$alasan)])->save();
            }
        }

        $this->audit->tindakan($status, 'permintaan_pergeseran', $m->id, $alasan, sistem: $status === 'DITOLAK_OTOMATIS');

        DB::afterCommit(function () use ($m, $status, $alasan) {
            $pengusul = \App\Models\User::query()->find($m->diajukan_oleh);
            $pengusul?->notify(new NotifikasiBlud('PERMINTAAN_'.$status, "Permintaan {$m->nomor} {$this->label($status)}", $alasan,
                "/pergeseran/permintaan/{$m->uuid}", true));
        });
    }

    public function kunci(PermintaanPergeseran $m): PermintaanPergeseran
    {
        /** @var PermintaanPergeseran */
        return PermintaanPergeseran::query()->whereKey($m->id)->lockForUpdate()->firstOrFail();
    }

    private function beritahuUnitSejenis(PermintaanPergeseran $m): void
    {
        $pagu = PaguJenis::query()->find($m->pagu_jenis_id);
        if ($pagu === null) {
            return;
        }
        $unitSejenis = PaguJenis::query()->where('tahun', $pagu->tahun)->where('jenis_id', $pagu->jenis_id)
            ->where('sumber_dana_id', $pagu->sumber_dana_id)->where('unit_id', '<>', $pagu->unit_id)->pluck('unit_id')->all();

        $penerima = $this->penerima->denganPermission('pergeseran.ajukan', $unitSejenis)
            ->merge($this->penerima->denganPermission('pergeseran.sahkan'))->unique('id');

        $arah = $m->arah === 'TAMBAH' ? 'membutuhkan tambahan' : 'melepas';
        Notification::send($penerima, new NotifikasiBlud('PERMINTAAN_BARU', "Permintaan pergeseran {$m->nomor}",
            "Unit {$arah} pagu ".Uang::dari((string) $m->nilai)->format().'. Batas pasangan: '.$m->batas_pasangan->format('d-m-Y H:i').' WIB.',
            '/pergeseran/terbuka'));
    }

    private function label(string $status): string
    {
        return match ($status) {
            'DITOLAK_OTOMATIS' => 'ditolak otomatis karena batas waktu pasangan terlampaui',
            'DIBATALKAN' => 'dibatalkan',
            'DITOLAK' => 'ditolak',
            default => strtolower($status),
        };
    }
}
