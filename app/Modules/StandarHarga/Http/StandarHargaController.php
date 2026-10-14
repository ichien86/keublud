<?php

declare(strict_types=1);

namespace App\Modules\StandarHarga\Http;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Domain\Uang;
use App\Modules\StandarHarga\Models\UsulanStandarBatch;
use App\Modules\StandarHarga\Models\UsulanStandarHarga;
use App\Modules\StandarHarga\Services\ImporUsulanStandarService;
use App\Modules\StandarHarga\Services\StandarHargaMatcher;
use App\Modules\StandarHarga\Services\UsulanStandarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StandarHargaController extends Controller
{
    public function __construct(
        private readonly StandarHargaMatcher $matcher,
        private readonly UsulanStandarService $usulan,
        private readonly ImporUsulanStandarService $impor,
    ) {}

    /** Halaman katalog, formulir usulan, dan keputusan perencana (PRD 7.4). */
    public function index(Request $request): Response
    {
        $daftarTahun = DB::table('standar_harga_item')->distinct()->orderBy('tahun')->pluck('tahun')->map(fn ($t) => (int) $t)->all();
        // Bawaan: tahun anggaran berjalan (RBA definitif terbaru); tahun berikutnya dipilih untuk penyusunan RBA.
        $tahun = $request->integer('tahun') ?: (int) (DB::table('rba')->where('status', 'DEFINITIF')->max('tahun') ?? (end($daftarTahun) ?: now()->year));
        $user = $this->pengguna($request);
        $db = DB::table('usulan_standar_harga as u')->leftJoin('users as p', 'p.id', '=', 'u.diajukan_oleh')
            ->where('u.tahun', $tahun)
            ->leftJoin('usulan_standar_batch as bt', 'bt.id', '=', 'u.batch_id')
            ->select(['bt.uuid as batch_uuid', 'u.id', 'u.uuid', 'u.uraian', 'u.spesifikasi', 'u.satuan', 'u.harga', 'u.kategori_cocok', 'u.status', 'u.alasan_keputusan',
                'u.dibuat_pada', 'u.diajukan_oleh', 'p.name as pengusul']);
        $menunggu = $user->can('standar.putuskan')
            ? (clone $db)->where('u.status', 'DIAJUKAN')->where('u.diajukan_oleh', '<>', $user->id)->orderBy('u.dibuat_pada')->limit(100)->get()
            : collect();
        $cocok = DB::table('usulan_kecocokan as k')->join('standar_harga_item as i', 'i.id', '=', 'k.item_id')
            ->whereIn('k.usulan_id', $menunggu->pluck('id'))->where('k.tahap', 'DIAJUKAN')->orderByDesc('k.skor')
            ->get(['k.usulan_id', 'k.skor', 'i.id', 'i.uraian', 'i.spesifikasi', 'i.satuan', 'i.harga'])->groupBy('usulan_id');

        return Inertia::render('StandarHarga/Index', [
            'tahun' => $tahun,
            'daftar_tahun' => $daftarTahun,
            'jumlah_item' => DB::table('standar_harga_item')->where('tahun', $tahun)->where('status', 'AKTIF')->count(),
            'usulan_saya' => (clone $db)->where('u.diajukan_oleh', $user->id)->orderByDesc('u.dibuat_pada')->limit(50)->get(),
            'usulan_menunggu' => $menunggu->map(fn ($u) => [...(array) $u, 'kecocokan' => $cocok->get($u->id, collect())->take(3)->values()]),
            'boleh_usul' => $user->can('standar.usul'),
            'boleh_putuskan' => $user->can('standar.putuskan'),
        ]);
    }

    /** Pengecekan kecocokan langsung saat pengusul mengetik (FR-017). */
    public function cocok(Request $request): JsonResponse
    {
        $d = $request->validate([
            'tahun' => ['required', 'integer'], 'uraian' => ['required', 'string', 'min:3'], 'spesifikasi' => ['nullable', 'string'],
            'satuan' => ['required', 'string'], 'harga' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'], 'kode_barang' => ['nullable', 'string'],
        ]);

        return response()->json(['data' => $this->matcher->cari((int) $d['tahun'], $d['uraian'], $d['spesifikasi'] ?? null,
            $d['satuan'], Uang::dari($d['harga']), $d['kode_barang'] ?? null)]);
    }

    /** Pencarian item untuk kolom item di halaman edit rincian. */
    public function cari(Request $request): JsonResponse
    {
        $d = $request->validate(['tahun' => ['required', 'integer'], 'q' => ['required', 'string', 'min:2']]);
        $item = DB::select(
            "SELECT id, sumber, kode_barang, uraian, spesifikasi, satuan, harga::text AS harga, tambahan_tengah_tahun
               FROM standar_harga_item
              WHERE tahun = ? AND status = 'AKTIF' AND berlaku_mulai <= current_date
                AND (uraian_norm ILIKE '%' || lower(unaccent_immutable(?)) || '%' OR kode_barang = ?)
              ORDER BY similarity(uraian_norm, lower(unaccent_immutable(?))) DESC
              LIMIT 20",
            [(int) $d['tahun'], $d['q'], $d['q'], $d['q']]
        );

        return response()->json(['data' => $item]);
    }

    public function ajukan(Request $request): JsonResponse
    {
        $d = $request->validate([
            'tahun' => ['required', 'integer'], 'uraian' => ['required', 'string', 'max:500'], 'spesifikasi' => ['nullable', 'string', 'max:1000'],
            'satuan' => ['required', 'string', 'max:50'], 'harga' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'kode_barang' => ['nullable', 'string', 'max:100'], 'kategori' => ['nullable', 'string', 'max:100'],
            'rekening_ids' => ['nullable', 'array'], 'rekening_ids.*' => ['integer'],
            'konteks' => ['nullable', 'in:PENYUSUNAN,REVISI,TRANSAKSI'], 'konteks_url' => ['nullable', 'string', 'max:500', 'starts_with:/'],
        ]);
        $u = $this->usulan->ajukan((int) $d['tahun'], $d, $this->pengguna($request));

        return response()->json(['uuid' => $u->uuid, 'kategori_cocok' => $u->kategori_cocok], 201);
    }

    public function putuskan(Request $request, UsulanStandarHarga $usulan): JsonResponse
    {
        $d = $request->validate([
            'keputusan' => ['required', 'in:SAHKAN,PAKAI_ITEM,KEMBALIKAN'],
            'item_rujukan_id' => ['nullable', 'integer', 'required_if:keputusan,PAKAI_ITEM'],
            'alasan' => ['nullable', 'string', 'max:2000'],
        ]);
        $u = $this->usulan->putuskan($usulan, $this->pengguna($request), $d['keputusan'], $d['item_rujukan_id'] ?? null, $d['alasan'] ?? null);

        return response()->json(['status' => $u->status]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ImporUsulanStandarService::KOLOM);
            fputcsv($out, ['', 'Reagen Hematologi 5 Diff', 'Kemasan 20 L', 'jerigen', '4750000', 'BMHP Laboratorium', '5.1.02.01.01.0012', 'Penawaran PT X 2026']);
            fclose($out);
        }, 'template-usulan-standar-harga.csv', ['Content-Type' => 'text/csv']);
    }

    public function imporPratinjau(Request $request): JsonResponse
    {
        $d = $request->validate(['tahun' => ['required', 'integer'], 'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);

        return response()->json($this->impor->pratinjau($request->file('file'), (int) $d['tahun'], $this->pengguna($request)));
    }

    public function imporAjukan(Request $request, UsulanStandarBatch $batch): JsonResponse
    {
        $d = $request->validate(['baris' => ['required', 'array', 'min:1']]);
        $b = $this->impor->ajukan($batch, $d['baris'], $this->pengguna($request));

        return response()->json(['status' => $b->status, 'jumlah' => $b->jumlah_baris]);
    }

    public function sahkanMemenuhiSyarat(Request $request, UsulanStandarBatch $batch): JsonResponse
    {
        return response()->json($this->usulan->sahkanMemenuhiSyarat($batch, $this->pengguna($request)));
    }
}
