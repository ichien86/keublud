<?php

declare(strict_types=1);

namespace App\Modules\Shared\Audit;

use Illuminate\Support\Facades\DB;

/**
 * Pencatatan tindakan domain (ajukan, setujui, tolak, setujui otomatis, ...) ke audit_log.
 * Perubahan data baris dicatat otomatis oleh trigger fn_audit(); hash berantai diisi trigger.
 */
final class AuditService
{
    /** @param array<string, mixed>|null $data */
    public function tindakan(string $tindakan, string $tabel, int $objekId, ?string $alasan = null, ?array $data = null, bool $sistem = false): void
    {
        DB::insert(
            "INSERT INTO audit_log (pengguna_id, aktor_jenis, atas_nama_id, tindakan, tabel, objek_id, nilai_baru, alasan, request_id, hash)
             VALUES (?, ?, NULLIF(current_setting('app.atas_nama_id', true), '')::bigint, ?, ?, ?, ?::jsonb, ?,
                     NULLIF(current_setting('app.request_id', true), '')::uuid, '\\x00'::bytea)",
            [
                $sistem ? null : auth()->id(),
                $sistem || auth()->id() === null ? 'SISTEM' : 'PENGGUNA',
                $tindakan, $tabel, $objekId,
                $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE),
                $alasan,
            ]
        );
    }

    /** Menandai bahwa operasi berikut dalam transaksi dijalankan oleh sistem (job terjadwal). */
    public static function sebagaiSistem(): void
    {
        DB::select("SELECT set_config('app.user_id', '', true), set_config('app.aktor_jenis', 'SISTEM', true)");
    }

    public static function denganAlasan(?string $alasan): void
    {
        DB::select("SELECT set_config('app.alasan', ?, true)", [$alasan ?? '']);
    }
}
