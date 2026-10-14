<?php

declare(strict_types=1);

namespace App\Modules\Revisi\Http;

use App\Http\Controllers\Controller;
use App\Modules\Anggaran\Models\PaguJenis;
use App\Modules\Anggaran\Models\Rba;
use App\Modules\Anggaran\Models\RbaRincian;
use App\Modules\Kodefikasi\Services\PemetaanService;
use App\Modules\Revisi\Models\RevisiDetail;
use App\Modules\Revisi\Models\RevisiPaket;
use App\Modules\Revisi\Services\RevisiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Halaman edit rincian dan aksi paket revisi (TDD 6.2–6.3). */
final class RevisiController extends Controller
{
    public function __construct(private readonly RevisiService $revisi, private readonly PemetaanService $pemetaan) {}

    public function edit(Request $request, PaguJenis $pagu): Response
    {
        $paket = $this->revisi->bukaDraf($pagu, $request->user());
        $rba = Rba::definitif($pagu->tahun);

        return Inertia::render('Revisi/Edit', [
            'pagu' => [
                'id' => $pagu->id, 'tahun' => $pagu->tahun, 'jenis_id' => $pagu->jenis_id,
                'unit' => $pagu->unit()->value('nama'),
                'jenis' => $pagu->jenis()->first(['kode', 'uraian']),
                'sumber_dana' => $pagu->sumberDana()->value('nama'),
                'pagu_berlaku' => (string) $pagu->pagu_berlaku, 'sisa' => (string) $pagu->sisa,
                'berlaku_langsung' => in_array($this->pemetaan->kodeJenis($pagu->jenis_id),
                    (array) app(\App\Modules\Shared\Konfigurasi::class)->get('revisi.jenis_berlaku_langsung'), true),
            ],
            'paket' => ['uuid' => $paket->uuid, 'row_version' => $paket->row_version],
            'rincian' => RbaRincian::query()->where('rba_id', $rba?->id)->where('aktif', true)
                ->where('unit_id', $pagu->unit_id)->where('jenis_id', $pagu->jenis_id)->where('sumber_dana_id', $pagu->sumber_dana_id)
                ->orderBy('id')->get(['id', 'item_id', 'rekening_blud_id', 'uraian', 'volume', 'satuan', 'harga', 'harga_standar', 'nilai',
                    'realisasi', 'komitmen', 'terkunci_paket_id', 'terkunci_transaksi', 'row_version']),
            'draf' => RevisiDetail::query()->where('paket_id', $paket->id)->orderBy('id')->get(['aksi', 'rincian_id', 'rincian_row_version', 'sesudah', 'di_atas_standar']),
            'ringkasan' => Inertia::defer(fn () => $this->revisi->ringkasan($paket)),
        ]);
    }

    public function simpanBaris(Request $request, RevisiPaket $paket): JsonResponse
    {
        $data = $request->validate([
            'baris' => ['present', 'array'],
            'baris.*.aksi' => ['required', 'in:TAMBAH,UBAH,HAPUS'],
            'baris.*.rincian_id' => ['nullable', 'integer', 'required_unless:baris.*.aksi,TAMBAH'],
            'baris.*.item_id' => ['nullable', 'integer', 'required_unless:baris.*.aksi,HAPUS'],
            'baris.*.rekening_blud_id' => ['nullable', 'integer', 'required_unless:baris.*.aksi,HAPUS'],
            'baris.*.volume' => ['nullable', 'regex:/^\d+(\.\d{1,4})?$/', 'required_unless:baris.*.aksi,HAPUS'],
            'baris.*.harga' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/', 'required_unless:baris.*.aksi,HAPUS'],
            'baris.*.uraian' => ['nullable', 'string', 'max:500'],
            'baris.*.sub_kegiatan_id' => ['nullable', 'integer'],
        ]);

        return response()->json($this->revisi->simpanBaris($paket, $request->user(), $data['baris']));
    }

    public function ringkasan(RevisiPaket $paket): JsonResponse
    {
        return response()->json($this->revisi->ringkasan($paket));
    }

    public function simpan(Request $request, RevisiPaket $paket): RedirectResponse|JsonResponse
    {
        $paket = $this->revisi->simpan($paket, $request->user(), $request->string('alasan')->toString() ?: null);

        return response()->json(['status' => $paket->status, 'nomor' => $paket->nomor]);
    }

    public function kirimPergeseran(Request $request, RevisiPaket $paket): JsonResponse
    {
        $paket = $this->revisi->kirimPergeseran($paket, $request->user(), $request->string('alasan')->toString() ?: null);
        $permintaan = $paket->permintaan()->first(['uuid', 'nomor', 'arah', 'nilai', 'batas_pasangan']);

        return response()->json(['status' => $paket->status, 'nomor' => $paket->nomor, 'permintaan' => $permintaan]);
    }

    public function bukti(Request $request, RevisiPaket $paket): JsonResponse
    {
        $d = $request->validate([
            'rincian_id' => ['nullable', 'integer'], 'item_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
        $this->revisi->lampirkanBukti($paket, $request->user(), $d['rincian_id'] ?? null, (int) $d['item_id'], $request->file('file'));

        return response()->json(['ok' => true], 201);
    }

    public function batalkan(Request $request, RevisiPaket $paket): JsonResponse
    {
        $this->revisi->batalkanDraf($paket, $request->user());

        return response()->json(['status' => 'DIBATALKAN']);
    }

    public function setujui(Request $request, RevisiPaket $paket): JsonResponse
    {
        $paket = $this->revisi->setujui($paket, $request->user(), $request->string('catatan')->toString() ?: null);

        return response()->json(['status' => $paket->status]);
    }

    public function tolak(Request $request, RevisiPaket $paket): JsonResponse
    {
        $request->validate(['catatan' => ['required', 'string', 'min:10']]);
        $paket = $this->revisi->tolak($paket, $request->user(), $request->string('catatan')->toString());

        return response()->json(['status' => $paket->status]);
    }
}
