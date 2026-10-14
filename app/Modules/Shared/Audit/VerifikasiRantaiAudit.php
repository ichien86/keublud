<?php

declare(strict_types=1);

namespace App\Modules\Shared\Audit;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Job harian: memverifikasi rantai hash audit_log (TDD 3.10). */
final class VerifikasiRantaiAudit implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function handle(): void
    {
        $rusak = DB::selectOne('SELECT fn_audit_verifikasi() AS id')->id;
        if ($rusak !== null) {
            Log::critical('Rantai hash audit_log terputus', ['audit_log_id' => $rusak]);
        }
        DB::table('konfigurasi')->updateOrInsert(
            ['kunci' => 'job.verifikasi_audit.terakhir'],
            ['nilai' => json_encode(['waktu' => now()->toIso8601String(), 'rusak' => $rusak]), 'diubah_pada' => now()]
        );
    }
}
