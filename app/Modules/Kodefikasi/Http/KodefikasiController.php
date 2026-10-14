<?php

declare(strict_types=1);

namespace App\Modules\Kodefikasi\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class KodefikasiController extends Controller
{
    /** Rekening BLUD yang dapat dianggarkan dan sudah dipetakan ke jenis belanja tertentu. */
    public function rekeningSejenis(Request $request): JsonResponse
    {
        $d = $request->validate(['jenis_id' => ['required', 'integer']]);
        $baris = DB::select(
            'SELECT b.id, b.kode, b.uraian
               FROM rekening b
               JOIN pemetaan_rekening p ON p.rekening_blud_id = b.id AND p.berlaku @> current_date
               JOIN rekening pm ON pm.id = p.rekening_pemda_id
              WHERE b.bagan = ? AND b.dapat_dianggarkan AND b.aktif AND pm.jenis_id = ?
              ORDER BY b.kode',
            ['BLUD', (int) $d['jenis_id']]
        );

        return response()->json(['data' => $baris]);
    }

    /** Rekening BLUD yang belum dipetakan (FR-003). */
    public function belumDipetakan(): JsonResponse
    {
        $baris = DB::select(
            "SELECT b.id, b.kode, b.uraian FROM rekening b
              WHERE b.bagan = 'BLUD' AND b.dapat_dianggarkan AND b.aktif
                AND NOT EXISTS (SELECT 1 FROM pemetaan_rekening p WHERE p.rekening_blud_id = b.id AND p.berlaku @> current_date)
              ORDER BY b.kode"
        );

        return response()->json(['data' => $baris]);
    }
}
