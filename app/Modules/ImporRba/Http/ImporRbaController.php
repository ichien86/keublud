<?php

declare(strict_types=1);

namespace App\Modules\ImporRba\Http;

use App\Http\Controllers\Controller;
use App\Modules\ImporRba\Services\ImporRbaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Halaman impor RBA format lama (TDD 8.4). */
final class ImporRbaController extends Controller
{
    public function __construct(private readonly ImporRbaService $impor) {}

    public function index(Request $request): Response
    {
        $this->pastikan($request);

        return Inertia::render('ImporRba/Index', [
            'impor' => DB::table('impor_rba as i')->join('impor_batch as b', 'b.id', '=', 'i.impor_batch_id')
                ->leftJoin('users as u', 'u.id', '=', 'i.dibuat_oleh')
                ->orderByDesc('i.dibuat_pada')->limit(30)
                ->get(['i.uuid', 'i.tahun', 'i.sheet', 'i.kelompok', 'i.status', 'i.dibuat_pada', 'b.nama_file', 'u.name as oleh',
                    DB::raw('(SELECT count(*) FROM staging_rba s WHERE s.impor_rba_id = i.id) AS jumlah'),
                    DB::raw("(SELECT count(*) FROM staging_rba s WHERE s.impor_rba_id = i.id AND s.status IN ('SIAP','DIABAIKAN')) AS selesai")]),
            'sumber_dana' => DB::table('sumber_dana')->where('aktif', true)->orderByRaw("kelompok <> 'PENDAPATAN_BLUD'")->orderBy('kode')->get(['id', 'kode', 'nama']),
            'batas_unggah_mb' => (int) floor(min($this->bytes(ini_get('upload_max_filesize')), $this->bytes(ini_get('post_max_size'))) / 1048576),
        ]);
    }

    public function unggah(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:61440']]);

        return response()->json($this->impor->unggah($request->file('file'), $this->pengguna($request)));
    }

    public function pratinjau(Request $request): JsonResponse
    {
        $d = $request->validate(['batch_uuid' => ['required', 'uuid'], 'sheet' => ['required', 'string'], 'kelompok' => ['required', 'integer', 'min:0', 'max:5']]);

        return response()->json($this->impor->pratinjau($d['batch_uuid'], $d['sheet'], (int) $d['kelompok'], $this->pengguna($request)));
    }

    public function mulai(Request $request): JsonResponse
    {
        $d = $request->validate([
            'batch_uuid' => ['required', 'uuid'], 'sheet' => ['required', 'string'], 'kelompok' => ['required', 'integer', 'min:0'],
            'tahun' => ['required', 'integer', 'between:2020,2100'], 'sumber_dana_id' => ['required', 'integer', 'exists:sumber_dana,id'],
        ]);

        return response()->json(['uuid' => $this->impor->mulai($d['batch_uuid'], $d['sheet'], (int) $d['kelompok'], (int) $d['tahun'], (int) $d['sumber_dana_id'], $this->pengguna($request))], 201);
    }

    public function tampil(Request $request, string $uuid): Response
    {
        $this->pastikan($request);
        $i = $this->cari($uuid);
        $this->impor->segarkan((int) $i->id);
        $i = $this->cari($uuid);

        $baris = DB::table('staging_rba as s')->leftJoin('unit_kerja as u', 'u.id', '=', 's.unit_id')
            ->leftJoin('usulan_standar_harga as us', 'us.id', '=', 's.usulan_id')
            ->where('s.impor_rba_id', $i->id)->orderBy('s.baris_sumber')->orderBy('s.id')
            ->get(['s.id', 's.baris_sumber', 's.kode_blud', 's.kode_pemda', 's.komponen', 's.rincian', 's.volume', 's.satuan', 's.harga',
                's.nilai_sumber', 's.gelondongan', 's.label_unit', 's.unit_id', 'u.nama as unit', 's.status', 's.alasan_abaikan',
                's.petunjuk', 's.peringatan', 's.rekening_blud_id', 'us.status as status_usulan']);
        $item = DB::table('staging_rba_item as x')->join('standar_harga_item as h', 'h.id', '=', 'x.item_id')
            ->whereIn('x.staging_id', $baris->pluck('id'))->orderBy('x.id')
            ->get(['x.staging_id', 'x.item_id', 'x.uraian', 'x.volume', 'x.harga', 'x.nilai', 'x.unit_id', 'h.satuan', 'h.harga as harga_standar'])
            ->groupBy('staging_id');

        return Inertia::render('ImporRba/Staging', [
            'impor' => [
                'uuid' => $i->uuid, 'tahun' => $i->tahun, 'sheet' => $i->sheet, 'kelompok' => $i->kelompok, 'status' => $i->status,
                'nama_file' => $i->nama_file, 'sumber_dana' => $i->sumber_dana, 'rba_id' => $i->rba_id,
                'kepala' => json_decode((string) $i->kepala, true), 'peringatan' => $this->pgArray($i->peringatan),
            ],
            'baris' => $baris->map(fn ($b) => [...(array) $b, 'petunjuk' => $this->pgArray($b->petunjuk), 'peringatan' => $this->pgArray($b->peringatan),
                'item' => $item->get($b->id, collect())->values()]),
            'rekonsiliasi' => $this->impor->rekonsiliasi((int) $i->id),
            // label dikelompokkan menurut bentuk ternormalisasi (UMUM = Umum = umum)
            'label' => collect(DB::select(
                "SELECT min(s.label_unit) AS label, p.unit_id, u.nama AS unit, count(*) AS jumlah
                   FROM staging_rba s
                   LEFT JOIN pemetaan_label_unit p ON p.label_norm = lower(btrim(regexp_replace(s.label_unit, '\\s+', ' ', 'g')))
                   LEFT JOIN unit_kerja u ON u.id = p.unit_id
                  WHERE s.impor_rba_id = ? AND s.label_unit IS NOT NULL
                  GROUP BY lower(btrim(regexp_replace(s.label_unit, '\\s+', ' ', 'g'))), p.unit_id, u.nama
                  ORDER BY 1", [$i->id])),
            'unit' => DB::table('unit_kerja')->where('aktif', true)->orderBy('nama')->get(['id', 'kode', 'nama']),
            'rekening_hilang' => DB::table('staging_rba')->where('impor_rba_id', $i->id)->whereNull('rekening_blud_id')->distinct()->count('kode_blud'),
            'boleh_kodefikasi' => $this->pengguna($request)->can('kodefikasi.kelola'),
            'boleh_usul' => $this->pengguna($request)->can('standar.usul'),
        ]);
    }

    public function kodefikasi(Request $request, string $uuid): JsonResponse
    {
        return response()->json($this->impor->lengkapiKodefikasi((int) $this->cari($uuid)->id, $this->pengguna($request)));
    }

    public function label(Request $request, string $uuid): JsonResponse
    {
        $d = $request->validate(['label' => ['required', 'string', 'max:200'], 'unit_id' => ['nullable', 'integer', 'exists:unit_kerja,id'], 'unit_baru' => ['nullable', 'string', 'max:200']]);
        $this->impor->petakanLabel((int) $this->cari($uuid)->id, $d['label'], $d['unit_id'] ?? null, $d['unit_baru'] ?? null, $this->pengguna($request));

        return response()->json(['ok' => true]);
    }

    public function item(Request $request, int $baris): JsonResponse
    {
        $d = $request->validate([
            'item' => ['present', 'array'], 'item.*.item_id' => ['required', 'integer'], 'item.*.volume' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
            'item.*.harga' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'], 'item.*.unit_id' => ['nullable', 'integer'], 'unit_id' => ['nullable', 'integer'],
        ]);
        $this->impor->simpanItem($baris, $d['item'], $d['unit_id'] ?? null, $this->pengguna($request));

        return response()->json(['ok' => true]);
    }

    public function abaikan(Request $request, int $baris): JsonResponse
    {
        $this->impor->abaikan($baris, $request->input('alasan'), $this->pengguna($request));

        return response()->json(['ok' => true]);
    }

    public function ajukanUsulan(Request $request, string $uuid): JsonResponse
    {
        return response()->json($this->impor->ajukanUsulan((int) $this->cari($uuid)->id, $this->pengguna($request)));
    }

    public function unitMassal(Request $request, string $uuid): JsonResponse
    {
        $d = $request->validate(['unit_id' => ['required', 'integer', 'exists:unit_kerja,id']]);

        return response()->json(['jumlah' => $this->impor->unitMassal((int) $this->cari($uuid)->id, (int) $d['unit_id'], $this->pengguna($request))]);
    }

    public function bentuk(Request $request, string $uuid): JsonResponse
    {
        return response()->json(['rba_id' => $this->impor->bentukRba((int) $this->cari($uuid)->id, $this->pengguna($request))]);
    }

    public function batalkan(Request $request, string $uuid): JsonResponse
    {
        $this->impor->batalkan((int) $this->cari($uuid)->id, $this->pengguna($request));

        return response()->json(['ok' => true]);
    }

    private function cari(string $uuid): object
    {
        return DB::table('impor_rba as i')->join('impor_batch as b', 'b.id', '=', 'i.impor_batch_id')
            ->join('sumber_dana as d', 'd.id', '=', 'i.sumber_dana_id')
            ->where('i.uuid', $uuid)->first(['i.*', 'b.nama_file', 'd.nama as sumber_dana'])
            ?? abort(404);
    }

    private function pastikan(Request $request): void
    {
        abort_unless($this->pengguna($request)->can('rba.impor'), 403, 'Anda tidak berwenang mengimpor RBA.');
    }

    /** @return list<string> */
    private function pgArray(?string $v): array
    {
        if ($v === null || $v === '{}') {
            return [];
        }
        $isi = substr($v, 1, -1);
        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|([^,]+)/', $isi, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => isset($x[2]) && $x[2] !== '' ? $x[2] : stripcslashes($x[1]), $m);
    }

    private function bytes(string|false $v): int
    {
        $v = trim((string) $v);
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n,
        };
    }
}
