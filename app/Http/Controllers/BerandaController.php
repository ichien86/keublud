<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Master\Services\AksesUnit;
use App\Modules\Workflow\Services\KotakMasukService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Beranda: pagu unit yang dapat diakses pengguna dan jumlah pengajuan yang menunggu keputusannya. */
final class BerandaController extends Controller
{
    public function __invoke(Request $request, AksesUnit $akses, KotakMasukService $kotak): Response
    {
        $user = $this->pengguna($request);
        $tahun = (int) (DB::table('rba')->where('status', 'DEFINITIF')->max('tahun') ?? now()->year);
        $lintasUnit = $user->hasAnyRole(['perencana', 'administrator', 'pimpinan_blud', 'pejabat_keuangan', 'verifikator', 'auditor']);
        $unit = $akses->unitPengguna($user->id);

        $pagu = DB::table('pagu_jenis as g')
            ->join('unit_kerja as u', 'u.id', '=', 'g.unit_id')
            ->join('rekening as j', 'j.id', '=', 'g.jenis_id')
            ->join('sumber_dana as s', 's.id', '=', 'g.sumber_dana_id')
            ->where('g.tahun', $tahun)
            ->when(! $lintasUnit, fn ($q) => $q->whereIn('g.unit_id', $unit))
            ->orderBy('u.nama')->orderBy('j.kode')
            ->get(['g.id', 'u.id as unit_id', 'u.nama as unit', 'j.kode as kode_jenis', 'j.uraian as jenis', 's.nama as sumber_dana',
                'g.pagu_berlaku', 'g.realisasi', 'g.komitmen', 'g.tercadang', 'g.sisa']);

        return Inertia::render('Beranda', [
            'tahun' => $tahun,
            'pagu' => $pagu,
            'boleh_edit_unit' => $unit,
            'boleh_revisi' => $user->can('revisi.ajukan'),
            'menunggu' => $kotak->jumlah($user),
            'peran' => $user->getRoleNames(),
        ]);
    }
}
