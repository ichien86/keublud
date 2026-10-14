<?php

declare(strict_types=1);

namespace App\Modules\Anggaran\Http;

use App\Http\Controllers\Controller;
use App\Modules\Anggaran\Models\Rba;
use App\Modules\Anggaran\Services\PersetujuanRbaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Daftar versi RBA dan pengajuannya ke alur verifikasi → persetujuan internal → penetapan. */
final class RbaController extends Controller
{
    public function index(Request $request): Response
    {
        $rba = DB::table('rba as r')
            ->leftJoin('persetujuan as p', fn ($j) => $j->on('p.objek_id', '=', 'r.id')->where('p.objek_tipe', 'RBA')->where('p.status', 'BERJALAN'))
            ->leftJoin('workflow_langkah as l', fn ($j) => $j->on('l.definisi_id', '=', 'p.definisi_id')->on('l.urutan', '=', 'p.langkah_aktif'))
            ->orderByDesc('r.tahun')->orderByDesc('r.versi')
            ->get(['r.id', 'r.tahun', 'r.versi', 'r.jenis_versi', 'r.status', 'r.nomor_pengesahan', 'r.tanggal_pengesahan', 'l.nama as langkah',
                DB::raw('(SELECT COALESCE(SUM(nilai),0) FROM rba_rincian x WHERE x.rba_id = r.id AND x.aktif)::text AS total'),
                DB::raw('(SELECT count(*) FROM rba_rincian x WHERE x.rba_id = r.id AND x.aktif) AS jumlah_rincian')]);

        return Inertia::render('Rba/Index', [
            'rba' => $rba,
            'boleh_ajukan' => $request->user()->can('rba.konsolidasi'),
        ]);
    }

    public function ajukan(Request $request, Rba $rba, PersetujuanRbaService $svc): JsonResponse
    {
        return response()->json(['status' => $svc->ajukan($rba, $request->user())->status]);
    }
}
