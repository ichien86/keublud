<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengisi konteks pelaku untuk trigger audit PostgreSQL (fn_audit) pada sesi koneksi.
 * Nilai di-reset setelah respons agar tidak bocor ke permintaan lain pada koneksi persisten.
 */
final class SetKonteksAudit
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        DB::select(
            "SELECT set_config('app.user_id', ?, false), set_config('app.aktor_jenis', 'PENGGUNA', false), set_config('app.request_id', ?, false)",
            [(string) ($request->user()?->id ?? ''), $requestId]
        );

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        DB::select("SELECT set_config('app.user_id', '', false), set_config('app.request_id', '', false), set_config('app.atas_nama_id', '', false)");
    }
}
