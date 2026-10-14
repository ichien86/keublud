<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Http;

use App\Http\Controllers\Controller;
use App\Modules\Anggaran\Models\Rba;
use App\Modules\Anggaran\Services\PersetujuanRbaService;
use App\Modules\Pergeseran\Models\PasanganPergeseran;
use App\Modules\Pergeseran\Services\PasanganService;
use App\Modules\Revisi\Models\RevisiPaket;
use App\Modules\Revisi\Services\RevisiService;
use App\Modules\Shared\Errors\KesalahanDomain;
use App\Modules\StandarHarga\Models\UsulanStandarHarga;
use App\Modules\StandarHarga\Services\UsulanStandarService;
use App\Modules\Workflow\Services\KotakMasukService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Kotak masuk persetujuan mobile-first (PRD 7.18, TDD 6.5). Satu pintu keputusan untuk semua jenis pengajuan. */
final class PersetujuanController extends Controller
{
    public function __construct(private readonly KotakMasukService $kotak) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Persetujuan/Index', [
            'item' => $this->kotak->untuk($request->user()),
            'perlu_pin' => $request->user()->pin_hash !== null,
        ]);
    }

    public function putuskan(Request $request, string $tipe, int $id): JsonResponse
    {
        $d = $request->validate([
            'keputusan' => ['required', 'in:SETUJUI,TOLAK'],
            'catatan' => ['nullable', 'string', 'max:2000', 'required_if:keputusan,TOLAK', Rule::when($request->input('keputusan') === 'TOLAK', ['min:10'])],
            'permintaan_id' => ['nullable', 'integer'],
            'kanal' => ['nullable', 'in:WEB,MOBILE'],
            'pin' => ['nullable', 'string', 'max:12'],
        ]);
        $user = $request->user();
        $kanal = $d['kanal'] ?? 'WEB';
        $metode = null;

        // Konfirmasi ulang (step-up) bila pengguna memiliki PIN (TDD 6.5).
        if ($user->pin_hash !== null) {
            if (! Hash::check((string) ($d['pin'] ?? ''), $user->pin_hash)) {
                throw new KesalahanDomain('PIN_SALAH', 'PIN konfirmasi tidak sesuai.', statusHttp: 422);
            }
            $metode = 'PIN';
        }

        $setuju = $d['keputusan'] === 'SETUJUI';
        $catatan = $d['catatan'] ?? null;

        $status = match ($tipe) {
            'RBA' => (function () use ($id, $user, $setuju, $catatan, $kanal, $metode) {
                $svc = app(PersetujuanRbaService::class);
                $rba = Rba::query()->findOrFail($id);

                return ($setuju ? $svc->setujui($rba, $user, $catatan, $kanal, $metode) : $svc->kembalikan($rba, $user, (string) $catatan, $kanal, $metode))->status;
            })(),
            'REVISI' => (function () use ($id, $user, $setuju, $catatan, $kanal, $metode) {
                $svc = app(RevisiService::class);
                $paket = RevisiPaket::query()->findOrFail($id);

                return ($setuju ? $svc->setujui($paket, $user, $catatan, $kanal, $metode) : $svc->tolak($paket, $user, (string) $catatan, $kanal, $metode))->status;
            })(),
            'PASANGAN' => (function () use ($id, $user, $setuju, $catatan, $kanal, $metode) {
                $svc = app(PasanganService::class);
                $p = PasanganPergeseran::query()->findOrFail($id);

                return ($setuju ? $svc->sahkan($p, $user, $catatan, $kanal, $metode) : $svc->tolak($p, $user, (string) $catatan, $kanal, $metode))->status;
            })(),
            'PASANGAN_UNIT' => app(PasanganService::class)->putuskanUnit(
                PasanganPergeseran::query()->findOrFail($id), (int) ($d['permintaan_id'] ?? 0), $user, $setuju, $catatan
            )->status,
            'USULAN_STANDAR' => app(UsulanStandarService::class)->putuskan(
                UsulanStandarHarga::query()->findOrFail($id), $user, $setuju ? UsulanStandarService::SAHKAN : UsulanStandarService::KEMBALIKAN, null, $catatan
            )->status,
            default => abort(404),
        };

        return response()->json(['status' => $status]);
    }
}
