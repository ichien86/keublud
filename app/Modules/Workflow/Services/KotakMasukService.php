<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Services;

use App\Models\User;
use App\Modules\Master\Services\AksesUnit;
use App\Modules\Workflow\Models\Persetujuan;
use Illuminate\Support\Facades\DB;

/**
 * Kotak masuk persetujuan lintas modul (PRD 7.18, tampilan mobile-first untuk Pimpinan BLUD).
 * Sumber: (1) persetujuan BERJALAN yang langkah aktifnya boleh diputuskan pengguna,
 * (2) persetujuan unit atas pasangan pergeseran, (3) usulan standar harga yang menunggu keputusan.
 */
final class KotakMasukService
{
    public function __construct(private readonly WorkflowService $workflow, private readonly AksesUnit $akses) {}

    /** @return list<array<string, mixed>> */
    public function untuk(User $user): array
    {
        $hasil = [];

        $berjalan = Persetujuan::query()->where('status', 'BERJALAN')->orderBy('dibuat_pada')->get();
        foreach ($berjalan as $p) {
            if (! $this->workflow->bolehMemutuskan($p, $user)) {
                continue;
            }
            $item = match (true) {
                $p->objek_tipe === 'RBA' => $this->rba($p),
                str_starts_with($p->objek_tipe, 'REVISI_') => $this->revisi($p),
                $p->objek_tipe === 'PASANGAN_PERGESERAN' => $this->pasangan($p),
                default => null,
            };
            if ($item !== null) {
                $hasil[] = $item;
            }
        }

        if ($user->can('pergeseran.setujui_unit')) {
            array_push($hasil, ...$this->persetujuanUnit($user));
        }
        if ($user->can('standar.putuskan')) {
            array_push($hasil, ...$this->usulanStandar($user));
        }

        usort($hasil, fn ($a, $b) => strcmp((string) ($a['batas'] ?? '9999'), (string) ($b['batas'] ?? '9999'))
            ?: strcmp((string) $a['diajukan_pada'], (string) $b['diajukan_pada']));

        return $hasil;
    }

    public function jumlah(User $user): int
    {
        return count($this->untuk($user));
    }

    private function dasar(string $tipe, int $id, string $label, string $judul, ?string $nilai, string $diajukanPada, ?string $pengusul): array
    {
        return [
            'tipe' => $tipe, 'id' => $id, 'label' => $label, 'judul' => $judul, 'nilai' => $nilai,
            'diajukan_pada' => $diajukanPada, 'pengusul' => $pengusul, 'batas' => null, 'rincian' => [],
            'aksi_setuju' => 'Setujui', 'aksi_tolak' => 'Tolak', 'permintaan_id' => null, 'peringatan' => null,
        ];
    }

    private function namaUser(?int $id): ?string
    {
        return $id ? DB::table('users')->where('id', $id)->value('name') : null;
    }

    private function rba(Persetujuan $p): ?array
    {
        $rba = DB::table('rba')->where('id', $p->objek_id)->first();
        if ($rba === null) {
            return null;
        }
        $langkah = DB::table('workflow_langkah')->where('definisi_id', $p->definisi_id)->where('urutan', $p->langkah_aktif)->value('nama');
        $item = $this->dasar('RBA', (int) $rba->id, 'RBA · '.$langkah, "RBA {$rba->tahun} versi {$rba->versi}",
            (string) $p->nilai, (string) $p->dibuat_pada, $this->namaUser((int) $p->diajukan_oleh));
        $item['rincian'] = DB::select(
            "SELECT j.kode || ' ' || j.uraian AS label, SUM(r.nilai)::text AS nilai
               FROM rba_rincian r JOIN rekening j ON j.id = r.jenis_id
              WHERE r.rba_id = ? AND r.aktif GROUP BY j.kode, j.uraian ORDER BY j.kode",
            [$rba->id]
        );
        $item['aksi_setuju'] = $langkah === 'Penetapan Pimpinan BLUD' ? 'Tetapkan' : 'Setujui';
        $item['aksi_tolak'] = 'Kembalikan';

        return $item;
    }

    private function revisi(Persetujuan $p): ?array
    {
        $r = DB::table('revisi_paket as k')->join('pagu_jenis as g', 'g.id', '=', 'k.pagu_jenis_id')
            ->join('unit_kerja as u', 'u.id', '=', 'g.unit_id')->join('rekening as j', 'j.id', '=', 'g.jenis_id')
            ->where('k.id', $p->objek_id)
            ->first(['k.id', 'k.nomor', 'k.alasan_menunggu', 'k.total_sebelum', 'k.total_sesudah', 'k.dibuat_oleh', 'k.alasan',
                'u.nama as unit', 'j.kode as kode_jenis', 'j.uraian as jenis']);
        if ($r === null) {
            return null;
        }
        $label = $r->alasan_menunggu === 'DI_ATAS_STANDAR' ? 'Revisi · harga di atas standar' : 'Revisi · perlu persetujuan';
        $item = $this->dasar('REVISI', (int) $r->id, $label, "{$r->nomor} · {$r->unit}", (string) $r->total_sesudah,
            (string) $p->dibuat_pada, $this->namaUser((int) $r->dibuat_oleh));
        $item['rincian'] = DB::table('revisi_detail')->where('paket_id', $r->id)->orderBy('id')->get(['aksi', 'sebelum', 'sesudah', 'di_atas_standar'])
            ->map(function ($d) {
                $s = json_decode((string) $d->sesudah, true) ?: [];
                $b = json_decode((string) $d->sebelum, true) ?: [];

                return [
                    'label' => $d->aksi.' · '.($s['uraian'] ?? $b['uraian'] ?? '-').($d->di_atas_standar ? ' (di atas standar)' : ''),
                    'nilai' => (string) ($s['nilai'] ?? $b['nilai'] ?? '0'),
                ];
            })->all();
        $item['peringatan'] = $r->alasan_menunggu === 'DI_ATAS_STANDAR' ? 'Memuat harga di atas standar. Periksa bukti harga pasar sebelum menyetujui.' : null;

        return $item;
    }

    private function pasangan(Persetujuan $p): ?array
    {
        $ps = DB::table('pasangan_pergeseran as p')->join('rekening as j', 'j.id', '=', 'p.jenis_id')
            ->where('p.id', $p->objek_id)->first(['p.*', 'j.kode as kode_jenis', 'j.uraian as jenis']);
        if ($ps === null) {
            return null;
        }
        $item = $this->dasar('PASANGAN', (int) $ps->id, 'Pergeseran antarunit', "{$ps->nomor} · {$ps->kode_jenis} {$ps->jenis}",
            (string) $ps->total_tambah, (string) $ps->dibentuk_pada, $this->namaUser((int) $ps->dibentuk_oleh));
        $item['batas'] = $ps->batas_pengesahan;
        $item['rincian'] = $this->anggota((int) $ps->id);
        $item['aksi_setuju'] = 'Sahkan';
        $item['peringatan'] = $ps->memuat_di_atas_standar ? 'Memuat harga di atas standar; tidak akan disetujui otomatis.' : null;

        return $item;
    }

    /** @return list<array{label: string, nilai: string}> */
    private function anggota(int $pasanganId): array
    {
        return array_map(fn ($a) => ['label' => ($a->arah === 'TAMBAH' ? '▲ ' : '▼ ').$a->unit.' · '.$a->persetujuan_unit, 'nilai' => (string) $a->nilai],
            DB::select(
                'SELECT m.arah, m.nilai::text AS nilai, u.nama AS unit, a.persetujuan_unit
                   FROM pasangan_anggota a JOIN permintaan_pergeseran m ON m.id = a.permintaan_id
                   JOIN pagu_jenis g ON g.id = m.pagu_jenis_id JOIN unit_kerja u ON u.id = g.unit_id
                  WHERE a.pasangan_id = ? AND a.aktif ORDER BY m.arah DESC, u.nama',
                [$pasanganId]
            ));
    }

    /** @return list<array<string, mixed>> */
    private function persetujuanUnit(User $user): array
    {
        $unit = $this->akses->unitPengguna($user->id);
        if ($unit === []) {
            return [];
        }
        $baris = DB::table('pasangan_anggota as a')
            ->join('pasangan_pergeseran as p', 'p.id', '=', 'a.pasangan_id')
            ->join('permintaan_pergeseran as m', 'm.id', '=', 'a.permintaan_id')
            ->join('pagu_jenis as g', 'g.id', '=', 'm.pagu_jenis_id')
            ->join('unit_kerja as u', 'u.id', '=', 'g.unit_id')
            ->join('rekening as j', 'j.id', '=', 'p.jenis_id')
            ->where('a.aktif', true)->where('a.persetujuan_unit', 'MENUNGGU')->where('p.status', 'MENUNGGU_UNIT')
            ->whereIn('g.unit_id', $unit)
            ->get(['p.id', 'p.nomor', 'p.dibentuk_pada', 'p.dibentuk_oleh', 'm.id as permintaan_id', 'm.arah', 'm.nilai', 'm.batas_pasangan',
                'u.nama as unit', 'j.kode as kode_jenis']);

        return $baris->map(function ($b) {
            $item = $this->dasar('PASANGAN_UNIT', (int) $b->id, 'Persetujuan unit · pergeseran',
                "{$b->nomor} · {$b->unit} ".($b->arah === 'TAMBAH' ? 'bertambah' : 'berkurang'),
                (string) $b->nilai, (string) $b->dibentuk_pada, $this->namaUser((int) $b->dibentuk_oleh));
            $item['permintaan_id'] = (int) $b->permintaan_id;
            $item['rincian'] = $this->anggota((int) $b->id);

            return $item;
        })->all();
    }

    /** @return list<array<string, mixed>> */
    private function usulanStandar(User $user): array
    {
        return DB::table('usulan_standar_harga')->where('status', 'DIAJUKAN')->where('diajukan_oleh', '<>', $user->id)
            ->orderBy('dibuat_pada')->limit(100)->get()
            ->map(function ($u) {
                $item = $this->dasar('USULAN_STANDAR', (int) $u->id, 'Usulan standar harga', trim("{$u->uraian} {$u->spesifikasi}").' / '.$u->satuan,
                    (string) $u->harga, (string) $u->dibuat_pada, $this->namaUser((int) $u->diajukan_oleh));
                $item['aksi_setuju'] = 'Sahkan';
                $item['aksi_tolak'] = 'Kembalikan';
                $item['peringatan'] = match ($u->kategori_cocok) {
                    'TINGGI' => 'Ada item standar yang sangat mirip. Pengesahan wajib disertai alasan.',
                    'SEDANG' => 'Ada item standar yang cukup mirip.',
                    default => null,
                };

                return $item;
            })->all();
    }
}
