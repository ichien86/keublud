<?php

declare(strict_types=1);

namespace App\Modules\Pergeseran\Http;

use App\Http\Controllers\Controller;
use App\Modules\Pergeseran\Models\PasanganPergeseran;
use App\Modules\Pergeseran\Models\PermintaanPergeseran;
use App\Modules\Pergeseran\Services\PasanganService;
use App\Modules\Pergeseran\Services\PermintaanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Daftar permintaan terbuka, pembentukan pasangan, persetujuan unit, dan pengesahan (TDD 6.2, 6.4). */
final class PergeseranController extends Controller
{
    public function __construct(private readonly PasanganService $pasangan, private readonly PermintaanService $permintaan) {}

    /** Halaman daftar permintaan terbuka dan pasangan (PRD 7.8a). */
    public function index(Request $request): Response
    {
        $pasangan = DB::table('pasangan_pergeseran as p')->join('rekening as j', 'j.id', '=', 'p.jenis_id')
            ->join('sumber_dana as s', 's.id', '=', 'p.sumber_dana_id')
            ->orderByRaw("CASE WHEN p.status IN ('MENUNGGU_UNIT','MENUNGGU_PERENCANA') THEN 0 ELSE 1 END")->orderByDesc('p.dibentuk_pada')
            ->limit(50)
            ->get(['p.id', 'p.uuid', 'p.nomor', 'p.status', 'p.total_tambah', 'p.batas_pengesahan', 'p.memuat_di_atas_standar', 'p.alasan',
                'j.kode as kode_jenis', 'j.uraian as jenis', 's.nama as sumber_dana']);
        $anggota = DB::table('pasangan_anggota as a')->join('permintaan_pergeseran as m', 'm.id', '=', 'a.permintaan_id')
            ->join('pagu_jenis as g', 'g.id', '=', 'm.pagu_jenis_id')->join('unit_kerja as u', 'u.id', '=', 'g.unit_id')
            ->whereIn('a.pasangan_id', $pasangan->pluck('id'))->where('a.aktif', true)
            ->get(['a.pasangan_id', 'a.permintaan_id', 'a.persetujuan_unit', 'm.nomor', 'm.arah', 'm.nilai', 'u.nama as unit'])
            ->groupBy('pasangan_id');

        return Inertia::render('Pergeseran/Index', [
            'terbuka' => $this->terbuka($request)->getData(true)['data'],
            'pasangan' => $pasangan->map(fn ($p) => [...(array) $p, 'anggota' => $anggota->get($p->id, collect())->values()]),
            'boleh_sahkan' => $this->pengguna($request)->can('pergeseran.sahkan'),
            'boleh_bentuk' => $this->pengguna($request)->can('pergeseran.ajukan') || $this->pengguna($request)->can('pergeseran.sahkan'),
        ]);
    }

    public function terbuka(Request $request): JsonResponse
    {
        $baris = DB::table('permintaan_pergeseran as m')
            ->join('pagu_jenis as g', 'g.id', '=', 'm.pagu_jenis_id')
            ->join('unit_kerja as u', 'u.id', '=', 'g.unit_id')
            ->join('rekening as j', 'j.id', '=', 'g.jenis_id')
            ->join('sumber_dana as s', 's.id', '=', 'g.sumber_dana_id')
            ->where('m.status', 'TERBUKA')
            ->when($request->integer('jenis_id'), fn ($q, $v) => $q->where('g.jenis_id', $v))
            ->when($request->integer('sumber_dana_id'), fn ($q, $v) => $q->where('g.sumber_dana_id', $v))
            ->orderBy('j.kode')->orderBy('s.kode')->orderBy('m.batas_pasangan')
            ->get(['m.id', 'm.uuid', 'm.nomor', 'm.arah', 'm.nilai', 'm.batas_pasangan', 'm.memuat_di_atas_standar',
                'u.nama as unit', 'j.kode as kode_jenis', 'j.uraian as jenis', 's.nama as sumber_dana', 'g.jenis_id', 'g.sumber_dana_id']);

        return response()->json(['data' => $baris]);
    }

    public function batalkanPermintaan(Request $request, PermintaanPergeseran $permintaan): JsonResponse
    {
        $this->permintaan->batalkan($permintaan, $this->pengguna($request));

        return response()->json(['status' => 'DIBATALKAN']);
    }

    public function bentuk(Request $request): JsonResponse
    {
        $data = $request->validate(['permintaan_ids' => ['required', 'array', 'min:2'], 'permintaan_ids.*' => ['integer']]);
        $p = $this->pasangan->bentuk(array_map('intval', $data['permintaan_ids']), $this->pengguna($request));

        return response()->json(['uuid' => $p->uuid, 'nomor' => $p->nomor, 'status' => $p->status], 201);
    }

    public function putuskanUnit(Request $request, PasanganPergeseran $pasangan): JsonResponse
    {
        $data = $request->validate([
            'permintaan_id' => ['required', 'integer'], 'setuju' => ['required', 'boolean'],
            'catatan' => ['nullable', 'string', 'required_if:setuju,false', 'min:10'],
        ]);
        $p = $this->pasangan->putuskanUnit($pasangan, (int) $data['permintaan_id'], $this->pengguna($request), (bool) $data['setuju'], $data['catatan'] ?? null);

        return response()->json(['status' => $p->status]);
    }

    public function sahkan(Request $request, PasanganPergeseran $pasangan): JsonResponse
    {
        $p = $this->pasangan->sahkan($pasangan, $this->pengguna($request), $request->string('catatan')->toString() ?: null);

        return response()->json(['status' => $p->status]);
    }

    public function tolak(Request $request, PasanganPergeseran $pasangan): JsonResponse
    {
        $request->validate(['catatan' => ['required', 'string', 'min:10']]);
        $p = $this->pasangan->tolak($pasangan, $this->pengguna($request), $request->string('catatan')->toString());

        return response()->json(['status' => $p->status]);
    }
}
