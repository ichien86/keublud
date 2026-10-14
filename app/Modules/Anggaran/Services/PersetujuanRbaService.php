<?php

declare(strict_types=1);

namespace App\Modules\Anggaran\Services;

use App\Models\User;
use App\Modules\Anggaran\Models\Rba;
use App\Modules\Shared\Domain\Uang;
use App\Modules\Shared\Errors\KesalahanDomain;
use App\Modules\Shared\Penomoran\PenomoranService;
use App\Modules\Workflow\Services\WorkflowService;
use Illuminate\Support\Facades\DB;

/**
 * Alur pengajuan RBA: verifikasi → persetujuan internal → penetapan Pimpinan BLUD (PRD 7.5, workflow RBA).
 * Langkah terakhir memanggil PenetapanRbaService sehingga pagu terbentuk dalam transaksi yang sama.
 */
final class PersetujuanRbaService
{
    public const WORKFLOW = 'RBA';

    /** Status RBA setelah langkah ke-n disetujui (langkah terakhir → DEFINITIF lewat penetapan). */
    private const STATUS_SETELAH = [1 => 'DIVERIFIKASI', 2 => 'MENUNGGU_PENETAPAN'];

    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly PenetapanRbaService $penetapan,
        private readonly PenomoranService $penomoran,
    ) {}

    public function ajukan(Rba $rba, User $user): Rba
    {
        if (! $user->can('rba.konsolidasi') && ! $user->can('rba.susun')) {
            throw new KesalahanDomain('TIDAK_BERWENANG', 'Anda tidak berwenang mengajukan RBA.', statusHttp: 403);
        }

        return DB::transaction(function () use ($rba, $user) {
            $rba = $this->kunci($rba, ['DRAF', 'DIKEMBALIKAN']);
            $total = (string) DB::table('rba_rincian')->where('rba_id', $rba->id)->where('aktif', true)->sum('nilai');
            if (! Uang::dari($total)->positif()) {
                throw new KesalahanDomain('RBA_KOSONG', 'RBA belum memiliki rincian belanja.');
            }
            $rba->forceFill(['status' => 'DIAJUKAN'])->save();
            $this->workflow->mulai(self::WORKFLOW, $rba->id, $user->id, $total);

            return $rba->refresh();
        }, attempts: 3);
    }

    public function setujui(Rba $rba, User $user, ?string $catatan = null, string $kanal = 'WEB', ?string $metode = null): Rba
    {
        return DB::transaction(function () use ($rba, $user, $catatan, $kanal, $metode) {
            $rba = $this->kunci($rba, ['DIAJUKAN', 'DIVERIFIKASI', 'MENUNGGU_PENETAPAN']);
            $p = $this->workflow->berjalan(self::WORKFLOW, $rba->id)
                ?? throw new KesalahanDomain('WORKFLOW_TIDAK_DITEMUKAN', 'Alur persetujuan RBA tidak ditemukan.');
            $langkah = (int) $p->langkah_aktif;

            if (! $this->workflow->setujui($p, $user, $catatan, $kanal, $metode)) {
                $rba->forceFill(['status' => self::STATUS_SETELAH[$langkah] ?? 'DISETUJUI_INTERNAL'])->save();

                return $rba->refresh();
            }

            if ($rba->status !== 'MENUNGGU_PENETAPAN') {
                $rba->forceFill(['status' => 'MENUNGGU_PENETAPAN'])->save();
            }

            return $this->penetapan->tetapkanDefinitif($rba, $this->penomoran->berikutnya('PENETAPAN_RBA', $rba->tahun), now());
        }, attempts: 3);
    }

    public function kembalikan(Rba $rba, User $user, string $catatan, string $kanal = 'WEB', ?string $metode = null): Rba
    {
        return DB::transaction(function () use ($rba, $user, $catatan, $kanal, $metode) {
            $rba = $this->kunci($rba, ['DIAJUKAN', 'DIVERIFIKASI', 'MENUNGGU_PENETAPAN']);
            $p = $this->workflow->berjalan(self::WORKFLOW, $rba->id)
                ?? throw new KesalahanDomain('WORKFLOW_TIDAK_DITEMUKAN', 'Alur persetujuan RBA tidak ditemukan.');
            $this->workflow->tolak($p, $user, $catatan, kembalikan: true, kanal: $kanal, metode: $metode);
            $rba->forceFill(['status' => 'DIKEMBALIKAN'])->save();

            return $rba->refresh();
        });
    }

    /** @param list<string> $status */
    private function kunci(Rba $rba, array $status): Rba
    {
        /** @var Rba $r */
        $r = Rba::query()->whereKey($rba->id)->lockForUpdate()->firstOrFail();
        if (! in_array($r->status, $status, true)) {
            throw new KesalahanDomain('STATUS_TIDAK_SESUAI', "RBA {$r->tahun} versi {$r->versi} berstatus {$r->status}.", statusHttp: 409);
        }

        return $r;
    }
}
